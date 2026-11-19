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
            ->where('received_at', '>=', $since)
            ->whereNotIn('reference', Payment::whereNotNull('kk_reference')->select('kk_reference'))
            ->orderByDesc('received_at')->get();

        return view('pos.clear', compact('bills', 'events', 'waiters', 'waiterId', 'isManager'));
    }

    /** Manager only: link a payment from the list (tap the payment, tick the bills, give a reason for other waiters' bills). */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'manager') {
            return back()->withErrors(['clear' => 'Type the M-Pesa code from the customer\'s SMS to clear a bill.']);
        }
        $data = $request->validate([
            'event_id' => 'required|integer',
            'bills' => 'required|array|min:1|max:30',
            'bills.*' => 'integer',
            'reason' => 'nullable|string|max:150',
        ], [
            'event_id.required' => 'Tap the M-Pesa payment first.',
            'bills.required' => 'Tick the bills this payment was for.',
        ]);

        // One link at a time, done by a person: at most one link every 10 seconds per login.
        if (! \Illuminate\Support\Facades\Cache::add('clear-link:' . $user->id, 1, 10)) {
            return back()->withErrors(['clear' => 'Slow down: check each payment before linking it. Try again in a few seconds.'])->withInput();
        }

        try {
            $msg = DB::transaction(function () use ($data, $user) {
                $event = KkEvent::where('id', $data['event_id'])->where('event_type', 'buygoods_transaction_received')->lockForUpdate()->first();
                return $this->link($event, $data['bills'], $user, (string) ($data['reason'] ?? ''));
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['clear' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pos.clear', $request->only('waiter'))->with('ok', $msg);
    }

    /**
     * Clear bills with the M-Pesa code the customer received by SMS. The code is only accepted if
     * Kopo Kopo really received it on our till and it is not already on another bill.
     */
    public function code(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'code' => ['required', 'regex:/^\s*[A-Za-z0-9]{8,12}\s*$/'],
            'bills' => 'required|array|min:1|max:30',
            'bills.*' => 'integer',
            'reason' => 'nullable|string|max:150',
        ], [
            'code.required' => 'Type the M-Pesa code from the customer\'s SMS.',
            'code.regex' => 'An M-Pesa code is 10 letters and numbers, e.g. UKILD26AD2.',
            'bills.required' => 'Tick the bill(s) this M-Pesa code paid for.',
        ]);
        $code = strtoupper(trim($data['code']));

        if (! \Illuminate\Support\Facades\Cache::add('clear-code:' . $user->id, 1, 3)) {
            return back()->withErrors(['clear' => 'One moment, then try again.'])->withInput();
        }

        try {
            $msg = DB::transaction(function () use ($data, $user, $code) {
                $event = KkEvent::where('event_type', 'buygoods_transaction_received')->where('reference', $code)->lockForUpdate()->first();
                if (! $event) {
                    throw new RuntimeException("Kopo Kopo has not received M-Pesa {$code}. Check the code on the customer's SMS (10 letters and numbers). If the customer paid cash, tell the manager.");
                }
                return $this->link($event, $data['bills'], $user, (string) ($data['reason'] ?? ''), true);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['clear' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pos.clear', $request->only('waiter'))->with('ok', $msg);
    }

    /** Puts one M-Pesa payment on the ticked bills. Runs inside a transaction; throws RuntimeException with a message for the waiter. */
    private function link(?KkEvent $event, array $billIds, User $user, string $reason, bool $byCode = false): string
    {
        if (! $event || $event->status !== 'pending') {
            throw new RuntimeException($event ? "M-Pesa {$event->reference} is already on another bill." : 'That M-Pesa payment is no longer available. It may already be matched.');
        }
        if (Payment::where('kk_reference', $event->reference)->exists()) {
            throw new RuntimeException("M-Pesa {$event->reference} is already on another bill.");
        }

        $ids = collect($billIds)->map(fn ($v) => (int) $v)->unique()->values();
        $list = Ticket::with(['items', 'payments', 'opener'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($list->count() !== $ids->count()) throw new RuntimeException('One of those bills no longer exists.');
        foreach ($list as $t) {
            if ($user->role !== 'manager' && $t->opened_by !== $user->id) {
                throw new RuntimeException("Bill {$t->order_no} is not yours.");
            }
            if ($user->role === 'manager' && ! $byCode && $t->opened_by !== $user->id && mb_strlen(trim($reason)) < 5) {
                throw new RuntimeException("Bill {$t->order_no} belongs to " . ($t->opener->name ?? 'another waiter')
                    . '. Write how you know this payment is for it (e.g. "order no. in Kopo Kopo", "waiter confirmed").');
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
            throw new RuntimeException("M-Pesa {$event->reference} was KSh " . number_format($paid) . ', less than the ticked bills together (KSh ' . number_format($sum) . '). Untick a bill.');
        }
        if ($paid - $sum > 50.004 && $user->role !== 'manager') {
            throw new RuntimeException("M-Pesa {$event->reference} was KSh " . number_format($paid) . ', KSh ' . number_format($paid - $sum) . ' more than the ticked bills. Tick the other bills it paid for, or ask a manager.');
        }

        $when = $event->received_at ?? now();
        foreach ($list->values() as $i => $t) {
            $amount = round((float) $t->balance, 2);
            if ($part) $amount = $paid;
            elseif ($i === $list->count() - 1) $amount = round($amount + ($paid - $sum), 2);
            Payment::create([
                'ticket_id' => $t->id, 'method' => 'kk_till_auto', 'amount' => $amount, 'kk_reference' => $event->reference,
                'received_at' => $when, 'matched_at' => now(), 'matched_by' => $user->id,
            ]);
            $fields = [];
            if (! $part) $fields += ['status' => 'paid', 'closed_at' => $when->max($t->opened_at ?? $when)];
            $note = $byCode ? "M-Pesa {$event->reference} code verified by {$user->name}." : (trim($reason) !== '' ? "M-Pesa {$event->reference} linked by {$user->name}: " . trim($reason) . '.' : '');
            if ($note !== '') $fields['notes'] = trim(($t->notes ? $t->notes . ' ' : '') . $note);
            if ($fields) $t->update($fields);
        }
        $event->update(['status' => 'matched']);

        $nos = $list->map(fn ($t) => '#' . $t->order_no)->implode(', ');
        return "M-Pesa {$event->reference} (KSh " . number_format($paid) . ') confirmed. ' . $nos . ($part ? ': part payment, KSh ' . number_format($sum - $paid) . ' still to pay.' : ($list->count() > 1 ? ' paid.' : ' paid.'))
            . ($paid - $sum > 0.004 ? ' KSh ' . number_format($paid - $sum) . ' extra put on the last bill.' : '');
    }

    /** Unpaid bills a waiter still has to clear: sent to the kitchen over an hour ago, within the "Clear my bills" window. */
    public static function pendingFor(User $user): array
    {
        if ($user->role === 'manager') return ['count' => 0, 'total' => 0.0];
        $bills = Ticket::with(['items', 'payments'])->where('opened_by', $user->id)->where('status', 'sent')
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'glovo'))
            ->where('opened_at', '>=', now()->subDays(self::DAYS - 1)->startOfDay())
            ->where('opened_at', '<=', now()->subHour())
            ->get()->filter(fn ($t) => $t->balance > 0.004);
        return ['count' => $bills->count(), 'total' => (float) $bills->sum(fn ($t) => $t->balance)];
    }
}
