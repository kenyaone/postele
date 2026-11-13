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

        $candidates = Ticket::with('items')
            ->where('status', 'sent')
            ->where('created_at', '<=', $paidAt)
            ->when(Schema::hasColumn('tickets', 'channel'), fn ($q) => $q->where(fn ($w) => $w->whereNull('channel')->orWhere('channel', '!=', 'glovo')))
            ->whereHas('items', fn ($q) => $q->where('sent_at', '>=', now()->subHour()))
            ->get()
            ->filter(fn ($t) => abs((float) $t->total - $amount) < 0.01)
            ->reject(fn ($t) => StkRequest::where('ticket_id', $t->id)->where('created_at', '>=', now()->subMinutes(3))->whereIn('status', ['pending', 'sent'])->exists())
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
}
