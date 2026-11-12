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

            PrintJob::create([
                'printer'       => 'cashier',
                'payload_bytes' => app(Receipts::class)->buildReceipt($t->fresh(['items.item', 'opener']), $payment),
                'status'        => 'pending',
            ]);
            return $payment;
        });
    }
}
