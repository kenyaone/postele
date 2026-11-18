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
    private bool $reclaiming = false;

    public function settle(Ticket $ticket, string $method, float $amount, ?string $reference, ?int $userId, bool $partial = false): Payment
    {
        return DB::transaction(function () use ($ticket, $method, $amount, $reference, $userId, $partial) {
            $t = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $t->load('items', 'payments');
            if (! in_array($t->status, ['open', 'sent'], true)) {
                throw new RuntimeException("Ticket #{$t->id} is already {$t->status}.");
            }
            if ($reference && Payment::where('kk_reference', $reference)->exists()) {
                throw new RuntimeException("M-Pesa code {$reference} has already been used.");
            }
            $due = $t->balance;
            if ($due <= 0.004) {
                throw new RuntimeException("Bill {$t->order_no} has nothing left to pay.");
            }
            if (! $partial && $amount + 0.004 < $due) {
                throw new RuntimeException('KSh ' . number_format($amount) . ' is less than what is left to pay on bill ' . $t->order_no . ' (KSh ' . number_format($due) . ').');
            }

            $payment = Payment::create([
                'ticket_id' => $t->id, 'method' => $method, 'amount' => $amount, 'kk_reference' => $reference,
                'received_at' => $t->backdated_by ? $t->opened_at : now(), 'matched_at' => now(), 'matched_by' => $userId,
            ]);

            // Part payment: the bill stays open until the rest is paid.
            if ($amount + 0.004 < $due) {
                return $payment;
            }

            $t->update(['status' => 'paid', 'closed_at' => $t->backdated_by ? $t->opened_at : now()]);
            if (! $t->backdated_by) app(\App\Services\PrintRouter::class)->cashier(app(Receipts::class)->buildReceipt($t->fresh(['items.item', 'opener', 'payments']), $payment), true, $t->id);
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
        if ($method === 'kk_stk' && $reference && ! $this->reclaiming) {
            return DB::transaction(function () use ($tickets, $method, $paid, $reference, $userId) {
                $this->reclaiming = true;
                try {
                    $ids = collect($tickets)->map(fn ($t) => $t instanceof Ticket ? $t->id : (int) $t)->all();
                    $this->reclaimGuess($reference, $ids);
                    return $this->settleMany($tickets, $method, $paid, $reference, $userId);
                } finally {
                    $this->reclaiming = false;
                }
            });
        }
        $ids = collect($tickets)->map(fn ($t) => $t instanceof Ticket ? $t->id : (int) $t)->unique()->values();
        if ($ids->count() === 1) {
            return [$this->settle(Ticket::findOrFail($ids[0]), $method, $paid, $reference, $userId)];
        }

        return DB::transaction(function () use ($ids, $method, $paid, $reference, $userId) {
            $list = Ticket::with(['items', 'payments'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($list->count() !== $ids->count()) throw new RuntimeException('One of those bills no longer exists.');
            foreach ($list as $t) {
                if (! in_array($t->status, ['open', 'sent'], true)) {
                    throw new RuntimeException("Bill {$t->order_no} is already {$t->status}.");
                }
            }
            if ($reference && Payment::where('kk_reference', $reference)->exists()) {
                throw new RuntimeException("M-Pesa code {$reference} has already been used.");
            }
            $sum = round((float) $list->sum(fn ($t) => $t->balance), 2);
            if ($paid + 0.004 < $sum) {
                throw new RuntimeException('KSh ' . number_format($paid) . ' is less than the bills together (KSh ' . number_format($sum) . ').');
            }

            $payments = [];
            foreach ($list->values() as $i => $t) {
                $amount = round((float) $t->balance, 2);
                if ($i === $list->count() - 1) $amount = round($amount + ($paid - $sum), 2);
                $payments[] = $payment = Payment::create([
                    'ticket_id' => $t->id, 'method' => $method, 'amount' => $amount, 'kk_reference' => $reference,
                    'received_at' => $t->backdated_by ? $t->opened_at : now(), 'matched_at' => now(), 'matched_by' => $userId,
                ]);
                $t->update(['status' => 'paid', 'closed_at' => $t->backdated_by ? $t->opened_at : now()]);
                if (! $t->backdated_by) app(\App\Services\PrintRouter::class)->cashier(app(Receipts::class)->buildReceipt($t->fresh(['items.item', 'opener', 'payments']), $payment), true, $t->id);
            }
            return $payments;
        });
    }

    /**
     * Takes a code off bills it was auto-guessed onto (till notice, no person involved) when a
     * phone prompt result says it pays other bills. Bills left short go back to unpaid.
     */
    private function reclaimGuess(string $reference, array $ids): void
    {
        $ids = array_map('intval', $ids);
        $taken = Payment::where('kk_reference', $reference)->lockForUpdate()->get();
        if ($taken->isEmpty()) {
            return;
        }
        $guessed = $taken->every(fn ($p) => $p->method === 'kk_till_auto' && $p->matched_by === null
            && ! in_array((int) $p->ticket_id, $ids, true));
        if (! $guessed) {
            return;
        }
        $first = Ticket::find($ids[0] ?? 0);
        foreach ($taken as $p) {
            $t = Ticket::with(['items', 'payments'])->lockForUpdate()->find($p->ticket_id);
            $p->delete();
            if ($t) {
                $t->load('payments');
                $fields = ['notes' => trim(($t->notes ? $t->notes . ' ' : '') . 'M-Pesa ' . $reference
                    . ' was auto-matched here by mistake; it was paid from the prompt for bill #' . ($first?->order_no ?? '?') . ', so it moved there.')];
                if ($t->status === 'paid' && $t->balance > 0.004) {
                    $fields += ['status' => 'sent', 'closed_at' => null];
                }
                $t->update($fields);
            }
            DB::table('payment_moves')->insert([
                'payment_id' => $p->id, 'kk_reference' => $reference, 'amount' => $p->amount,
                'from_ticket_id' => $p->ticket_id, 'to_ticket_id' => $ids[0] ?? 0,
                'reason' => 'Auto-fix: phone prompt result named another bill', 'moved_by' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
