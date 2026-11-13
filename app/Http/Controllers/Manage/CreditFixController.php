<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\CreditSale;
use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fix bills that waiters closed with "Credit" although the customer really paid:
 * link them to the M-Pesa till payment that paid them, or mark them paid in cash.
 * No receipts are printed; the bill keeps its order number.
 */
class CreditFixController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        try {
            $date = $request->filled('date') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay() : $today;
        } catch (\Throwable $e) {
            $date = $today;
        }
        if ($date->gt($today)) $date = $today;
        [$from, $to] = [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

        $bills = CreditSale::with(['ticket.opener', 'ticket.items.item', 'customer'])
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get()
            ->filter(fn ($c) => $c->ticket)->values();

        $events = KkEvent::where('event_type', 'buygoods_transaction_received')->where('status', 'pending')
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get()
            ->reject(fn ($e) => $e->reference && Payment::where('kk_reference', $e->reference)->exists())->values();

        // For each payment: the credit bills from 90 minutes before to 30 minutes after, nearest first.
        $cards = $events->map(function ($e) use ($bills) {
            $at = $e->received_at ?? $e->created_at;
            $near = $bills->filter(fn ($b) => $b->created_at->between($at->copy()->subMinutes(90), $at->copy()->addMinutes(30)))
                ->sortBy(fn ($b) => abs($b->created_at->diffInSeconds($at)))->values();
            return (object) ['e' => $e, 'at' => $at, 'name' => $e->payerName(), 'near' => $near];
        });

        return view('manage.credit-fix', [
            'date'  => $date,
            'bills' => $bills,
            'cards' => $cards,
            'owed'  => $bills->sum('amount'),
            'loose' => $events->sum('amount'),
        ]);
    }

    /** Link one till payment to one or more credit bills. */
    public function link(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|integer|exists:kk_events,id',
            'bills'    => 'required|array|min:1|max:20',
            'bills.*'  => 'integer',
        ], ['bills.required' => 'Tick the bill or bills this M-Pesa paid.']);

        try {
            $msg = DB::transaction(function () use ($data, $request) {
                $e = KkEvent::lockForUpdate()->findOrFail($data['event_id']);
                if ($e->status !== 'pending' || ($e->reference && Payment::where('kk_reference', $e->reference)->exists())) {
                    throw new \RuntimeException("M-Pesa {$e->reference} is already linked to a bill.");
                }
                $sales = CreditSale::with('ticket.items')->whereIn('ticket_id', $data['bills'])->lockForUpdate()->get();
                if ($sales->count() !== count(array_unique($data['bills']))) {
                    throw new \RuntimeException('One of those bills is no longer on credit. Refresh the page.');
                }
                $sum = round((float) $sales->sum('amount'), 2);
                if ((float) $e->amount + 0.004 < $sum) {
                    throw new \RuntimeException('M-Pesa ' . $e->reference . ' was KSh ' . number_format((float) $e->amount) . ', less than the ticked bills (KSh ' . number_format($sum) . ').');
                }
                $at = $e->received_at ?? $e->created_at;
                foreach ($sales->values() as $i => $s) {
                    $amount = round((float) $s->amount + ($i === $sales->count() - 1 ? (float) $e->amount - $sum : 0), 2);
                    Payment::where('ticket_id', $s->ticket_id)->where('method', 'credit')->update([
                        'method' => 'kk_till_auto', 'amount' => $amount, 'kk_reference' => $e->reference,
                        'received_at' => $at, 'matched_at' => now(), 'matched_by' => $request->user()->id,
                    ]);
                    $s->delete();
                }
                $e->update(['status' => 'matched']);
                return 'M-Pesa ' . $e->reference . ' linked: ' . $sales->map(fn ($s) => $s->ticket->order_no)->implode(', ') . ' now paid.';
            });
        } catch (\RuntimeException $ex) {
            return back()->withErrors(['fix' => $ex->getMessage()]);
        }

        return back()->with('ok', $msg);
    }

    /** The customer paid this credit bill in cash. */
    public function cash(Request $request, Ticket $ticket)
    {
        $s = CreditSale::where('ticket_id', $ticket->id)->first();
        if (! $s) return back()->withErrors(['fix' => "Bill {$ticket->order_no} is not on credit any more."]);
        DB::transaction(function () use ($s, $ticket, $request) {
            Payment::where('ticket_id', $ticket->id)->where('method', 'credit')
                ->update(['method' => 'cash', 'matched_at' => now(), 'matched_by' => $request->user()->id]);
            $s->delete();
        });
        return back()->with('ok', "Bill {$ticket->order_no} marked paid in cash. Make sure the cash is collected from the waiter.");
    }
}
