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
 * Two or more fits -> leave it for the waiter to pick. If no single bill fits, one group of 2-5 bills by the
 * same waiter that adds up exactly is paid together. Returns the (first) ticket it paid, or null.
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
        if ($candidates->count() > 1) return null;   // several single bills fit: the waiter picks

        // No single bill fits: maybe one person paid for several bills of the same waiter.
        // Use it only when exactly ONE group of 2-5 bills adds up to the amount exactly.
        $pool = Ticket::with('items')
            ->where('status', 'sent')
            ->where('created_at', '<=', $paidAt)
            ->when(Schema::hasColumn('tickets', 'channel'), fn ($q) => $q->where(fn ($w) => $w->whereNull('channel')->orWhere('channel', '!=', 'glovo')))
            ->whereHas('items', fn ($q) => $q->where('sent_at', '>=', now()->subHour()))
            ->get()
            ->reject(fn ($t) => StkRequest::where('ticket_id', $t->id)->where('created_at', '>=', now()->subMinutes(3))->whereIn('status', ['pending', 'sent'])->exists());
        $found = [];
        foreach ($pool->groupBy('opened_by') as $bills) {
            $bills = $bills->values()->take(10);
            $n = $bills->count();
            for ($mask = 1; $mask < (1 << $n); $mask++) {
                $c = substr_count(decbin($mask), '1');
                if ($c < 2 || $c > 5) continue;
                $pick = []; $sum = 0.0;
                for ($i = 0; $i < $n; $i++) if ($mask & (1 << $i)) { $pick[] = $bills[$i]; $sum += (float) $bills[$i]->total; }
                if (abs($sum - $amount) < 0.01) { $found[] = $pick; if (count($found) > 1) return null; }
            }
        }
        if (count($found) !== 1) return null;

        $this->settler->settleMany($found[0], 'kk_till_auto', $amount, $event->reference, null);
        $event->update(['status' => 'matched']);
        return $found[0][0];
    }
}
