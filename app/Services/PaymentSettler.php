<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Records a payment against a ticket, closes it, and queues the receipt. Used by cash-free methods. */
class PaymentSettler
{
    public function settle(Ticket $ticket, string $method, float $amount, ?string $reference, ?int $userId): Payment
    {
        return DB::transaction(function () use ($ticket, $method, $amount, $reference, $userId) {
            $t = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($t->status, ['open', 'sent'], true)) {
                throw new RuntimeException("Ticket #{$t->id} is already {$t->status}.");
            }
            if ($reference && Payment::where('kk_reference', $reference)->exists()) {
                throw new RuntimeException("M-Pesa code {$reference} has already been used.");
            }

            $payment = Payment::create([
                'ticket_id' => $t->id, 'method' => $method, 'amount' => $amount, 'kk_reference' => $reference,
                'received_at' => now(), 'matched_at' => now(), 'matched_by' => $userId,
            ]);
            $t->update(['status' => 'paid', 'closed_at' => now()]);

            app(\App\Services\PrintRouter::class)->cashier(app(Receipts::class)->buildReceipt($t->fresh(['items.item', 'opener']), $payment), true, $t->id);
            return $payment;
        });
    }

    /**
     * One M-Pesa payment that covers several bills (e.g. one person paying for a group or an office).
     * Each bill gets a payment row for its own total, all with the same M-Pesa code; anything paid
     * over the combined total goes on the last bill so the rows add up to what Kopo Kopo received.
     *
     * @param  iterable<Ticket>  $tickets
     * @return Payment[]
     */
    public function settleMany(iterable $tickets, string $method, float $paid, ?string $reference, ?int $userId): array
    {
        $ids = collect($tickets)->map(fn ($t) => $t instanceof Ticket ? $t->id : (int) $t)->unique()->values();
        if ($ids->count() === 1) {
            return [$this->settle(Ticket::findOrFail($ids[0]), $method, $paid, $reference, $userId)];
        }

        return DB::transaction(function () use ($ids, $method, $paid, $reference, $userId) {
            $list = Ticket::with('items')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($list->count() !== $ids->count()) throw new RuntimeException('One of those bills no longer exists.');
            foreach ($list as $t) {
                if (! in_array($t->status, ['open', 'sent'], true)) {
                    throw new RuntimeException("Bill {$t->order_no} is already {$t->status}.");
                }
            }
            if ($reference && Payment::where('kk_reference', $reference)->exists()) {
                throw new RuntimeException("M-Pesa code {$reference} has already been used.");
            }
            $sum = round((float) $list->sum(fn ($t) => $t->total), 2);
            if ($paid + 0.004 < $sum) {
                throw new RuntimeException('KSh ' . number_format($paid) . ' is less than the bills together (KSh ' . number_format($sum) . ').');
            }

            $payments = [];
            foreach ($list->values() as $i => $t) {
                $amount = round((float) $t->total, 2);
                if ($i === $list->count() - 1) $amount = round($amount + ($paid - $sum), 2);
                $payments[] = $payment = Payment::create([
                    'ticket_id' => $t->id, 'method' => $method, 'amount' => $amount, 'kk_reference' => $reference,
                    'received_at' => now(), 'matched_at' => now(), 'matched_by' => $userId,
                ]);
                $t->update(['status' => 'paid', 'closed_at' => now()]);
                app(\App\Services\PrintRouter::class)->cashier(app(Receipts::class)->buildReceipt($t->fresh(['items.item', 'opener']), $payment), true, $t->id);
            }
            return $payments;
        });
    }
}
