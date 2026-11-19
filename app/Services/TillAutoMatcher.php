<?php

namespace App\Services;

use App\Models\KkEvent;
use App\Models\StkRequest;
use App\Models\Ticket;

/**
 * A customer paid the till directly (Buy Goods), so the payment carries no order number.
 * It is linked automatically ONLY when the bill is known:
 *  - a POS prompt / bill QR for that amount is still waiting (its own result will close the right bill), or
 *  - exactly one bill is marked "paying from the Kopo Kopo app / till" for exactly that amount in the last 20 minutes.
 * Matching by amount alone is never done: the waiter links everything else on "Clear my bills".
 */
class TillAutoMatcher
{
    public function __construct(private PaymentSettler $settler) {}

    public function match(KkEvent $event): ?Ticket
    {
        if ($event->status !== 'pending' || ! $event->reference || (float) $event->amount <= 0) return null;
        $paidAt = $event->received_at ?? $event->created_at ?? now();
        $amount = (float) $event->amount;

        // 0. A phone prompt (QR on the bill, or "Send prompt") for this amount went out shortly before
        //    and has no result yet. This is probably that payment, and the prompt's own result says
        //    which bill it is for, so don't guess.
        if ($this->promptWaiting($amount, $paidAt)) {
            return null;
        }

        // 1. A waiter tapped "Customer is paying from the Kopo Kopo app / till" on a bill of exactly this amount
        //    in the last 20 minutes. Link only when exactly ONE such bill is waiting; two or more means we cannot
        //    tell whose customer paid, so the waiter links it on "Clear my bills".
        $waiting = $this->waitingBills($amount, $paidAt);
        if ($waiting->count() === 1) {
            $t = $waiting->first();
            $this->settler->settle($t->ticket, 'kk_till_auto', $amount, $event->reference, null);
            $t->update(['status' => 'success']);
            $event->update(['status' => 'matched']);
            return $t->ticket;
        }

        // No guessing by amount alone: many bills share the same price, so a payment without a bill
        // (no POS prompt, no QR, no claim) always waits for the waiter.
        return null;
    }

    /** "Waiting for app/till payment" claims for this amount, made in the 20 minutes up to the payment. */
    public function waitingBills(float $amount, $paidAt): \Illuminate\Support\Collection
    {
        return StkRequest::with(['ticket.items', 'ticket.payments'])->where('phone', 'kk-app')->where('status', 'pending')
            ->where('created_at', '>=', $paidAt->copy()->subMinutes(20))->orderBy('id')->get()
            ->filter(fn ($s) => $s->ticket && in_array($s->ticket->status, ['open', 'sent'], true) && abs((float) $s->ticket->balance - $amount) < 0.01)
            ->unique('ticket_id')->values();
    }

    /** A phone prompt for this amount (one bill, or several bills sent together) that is still waiting. */
    public function promptWaiting(float $amount, $paidAt): bool
    {
        $pending = StkRequest::where('phone', '!=', 'kk-app')->where('status', 'pending')
            ->where('created_at', '>=', $paidAt->copy()->subMinutes(5))->get();
        if ($pending->contains(fn ($s) => abs((float) $s->amount - $amount) < 0.01)) {
            return true;
        }
        return $pending->groupBy(fn ($s) => $s->created_at->format('YmdHis') . '-' . $s->phone)
            ->contains(fn ($g) => abs((float) $g->sum('amount') - $amount) < 0.01);
    }
}
