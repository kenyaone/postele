<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Clear my bills": a waiter links till payments that arrived without a bill (Kopo Kopo app prompts,
 * customers paying the till) to their own unpaid bills. Unlike the bill screen (last 3 hours, 15 bills),
 * it lists every unpaid bill and every unlinked till payment from the last 3 days, so the day can be closed.
 * The payment keeps the time the money arrived, so waiter pay counts it on the right day.
 */
class ClearController extends Controller
{
    private const DAYS = 3;

    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = $user->role === 'manager';
        $waiterId = $isManager ? (int) $request->query('waiter', 0) : $user->id;
        $since = now()->subDays(self::DAYS - 1)->startOfDay();

        $all = Ticket::with(['items.item', 'payments', 'opener'])
            ->whereIn('status', ['open', 'sent'])
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'glovo'))
            ->where('opened_at', '>=', $since)
            ->orderBy('opened_at')->get()
            ->filter(fn ($t) => $t->balance > 0.004)->values();

        $waiters = $isManager
            ? User::whereIn('id', $all->pluck('opened_by')->unique())->orderBy('name')->get(['id', 'name'])
            : collect();
        $bills = $waiterId ? $all->where('opened_by', $waiterId)->values() : $all;

        $events = KkEvent::where('event_type', 'buygoods_transaction_received')->where('status', 'pending')
            ->where('received_at', '>=', $since)->orderByDesc('received_at')->get()
            ->reject(fn ($e) => Payment::where('kk_reference', $e->reference)->exists())->values();

        return view('pos.clear', compact('bills', 'events', 'waiters', 'waiterId', 'isManager'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|integer',
            'bills' => 'required|array|min:1|max:30',
            'bills.*' => 'integer',
        ], [
            'event_id.required' => 'Tap the M-Pesa payment first.',
            'bills.required' => 'Tick the bills this payment was for.',
        ]);
        $user = $request->user();

        try {
            $msg = DB::transaction(function () use ($data, $user) {
                $event = KkEvent::where('id', $data['event_id'])->where('event_type', 'buygoods_transaction_received')->lockForUpdate()->first();
                if (! $event || $event->status !== 'pending') {
                    throw new RuntimeException('That M-Pesa payment is no longer available. It may already be matched.');
                }
                if (Payment::where('kk_reference', $event->reference)->exists()) {
                    throw new RuntimeException("M-Pesa {$event->reference} has already been used.");
                }

                $ids = collect($data['bills'])->map(fn ($v) => (int) $v)->unique()->values();
                $list = Ticket::with(['items', 'payments'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($list->count() !== $ids->count()) throw new RuntimeException('One of those bills no longer exists.');
                foreach ($list as $t) {
                    if ($user->role !== 'manager' && $t->opened_by !== $user->id) {
                        throw new RuntimeException("Bill {$t->order_no} is not yours.");
                    }
                    if (! in_array($t->status, ['open', 'sent'], true)) {
                        throw new RuntimeException("Bill {$t->order_no} is already {$t->status}.");
                    }
                    if ($t->balance <= 0.004) throw new RuntimeException("Bill {$t->order_no} has nothing left to pay.");
                }

                $paid = round((float) $event->amount, 2);
                $sum = round((float) $list->sum(fn ($t) => $t->balance), 2);
                $part = $paid + 0.004 < $sum;
                if ($part && $list->count() > 1) {
                    throw new RuntimeException('KSh ' . number_format($paid) . ' is less than the ticked bills together (KSh ' . number_format($sum) . '). Untick a bill.');
                }

                if ($paid - $sum > 50.004 && $user->role !== 'manager') {
                    throw new RuntimeException('KSh ' . number_format($paid) . ' is KSh ' . number_format($paid - $sum) . ' more than the ticked bills. Tick the other bills it paid for, or ask a manager.');
                }

                $when = $event->received_at ?? now();
                foreach ($list->values() as $i => $t) {
                    $amount = round((float) $t->balance, 2);
                    if ($i === $list->count() - 1) $amount = round($amount + ($paid - $sum), 2);
                    Payment::create([
                        'ticket_id' => $t->id, 'method' => 'kk_till_auto', 'amount' => $amount, 'kk_reference' => $event->reference,
                        'received_at' => $when, 'matched_at' => now(), 'matched_by' => $user->id,
                    ]);
                    if (! $part) $t->update(['status' => 'paid', 'closed_at' => $when->max($t->opened_at ?? $when)]);
                }
                $event->update(['status' => 'matched']);

                $nos = $list->map(fn ($t) => '#' . $t->order_no)->implode(', ');
                return "M-Pesa {$event->reference} (KSh " . number_format($paid) . ') linked to ' . $nos . '. '
                    . ($part ? 'Part payment: KSh ' . number_format($sum - $paid) . ' still to pay.' : ($list->count() > 1 ? 'Bills paid.' : 'Bill paid.'))
                    . ($paid - $sum > 0.004 ? ' KSh ' . number_format($paid - $sum) . ' extra put on the last bill.' : '');
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['clear' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pos.clear', $request->only('waiter'))->with('ok', $msg);
    }
}
