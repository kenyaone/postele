<?php

namespace App\Services;

use App\Models\KkEvent;
use App\Models\StkRequest;
use App\Models\Ticket;
use Illuminate\Support\Facades\Schema;

/**
 * A customer paid the till directly (Buy Goods), so the payment carries no order number.
 * Link it to an order only when exactly ONE unpaid order fits:
 *  - status "sent" (in the kitchen, not yet paid), not a Glovo order
 *  - dishes sent to the kitchen within the last hour, and opened before the payment came in
 *  - order total equals the amount paid exactly
 *  - no M-Pesa prompt for that order in the last 3 minutes (that prompt may be about to pay it)
 * Two or more fits, or none -> leave it for the waiter. One payment for several bills is only ever linked by the
 * waiter choosing the bills (order numbers) on the POS. Returns the ticket it paid, or null.
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
        //    in the last 20 minutes: the oldest such bill gets this payment.
        if ($t = $this->waitingBill($amount, $paidAt)) {
            $this->settler->settle($t->ticket, 'kk_till_auto', $amount, $event->reference, null);
            $t->update(['status' => 'success']);
            $event->update(['status' => 'matched']);
            return $t->ticket;
        }

        $candidates = Ticket::with(['items', 'payments'])
            ->where('status', 'sent')
            ->where('created_at', '<=', $paidAt)
            ->when(Schema::hasColumn('tickets', 'channel'), fn ($q) => $q->where(fn ($w) => $w->whereNull('channel')->orWhere('channel', '!=', 'glovo')))
            ->whereHas('items', fn ($q) => $q->where('sent_at', '>=', now()->subHour()))
            ->get()
            ->filter(fn ($t) => abs((float) $t->balance - $amount) < 0.01)
            ->reject(fn ($t) => StkRequest::where('ticket_id', $t->id)->where('created_at', '>=', now()->subMinutes(3))->whereIn('status', ['pending', 'sent'])->where('phone', '!=', 'kk-app')->exists())
            ->values();

        if ($candidates->count() === 1) {
            $ticket = $candidates->first();
            $this->settler->settle($ticket, 'kk_till_auto', $amount, $event->reference, null);
            $event->update(['status' => 'matched']);
            return $ticket;
        }
        // Several bills together are never merged automatically: the waiter picks them by order number on the POS.
        return null;
    }

    /** Oldest "waiting for app/till payment" claim for this amount, made in the 20 minutes up to the payment. */
    public function waitingBill(float $amount, $paidAt): ?StkRequest
    {
        return StkRequest::with(['ticket.items', 'ticket.payments'])->where('phone', 'kk-app')->where('status', 'pending')
            ->where('created_at', '>=', $paidAt->copy()->subMinutes(20))->orderBy('id')->get()
            ->first(fn ($s) => $s->ticket && in_array($s->ticket->status, ['open', 'sent'], true) && abs((float) $s->ticket->balance - $amount) < 0.01);
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
