<?php

namespace App\Console\Commands;

use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\StkRequest;
use App\Services\KopoKopo;
use App\Services\PaymentSettler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Safety net for lost webhooks: asks Kopo Kopo directly about every M-Pesa prompt that has had
 * no answer for over a minute, and closes the bill exactly as the webhook would have.
 */
class MpesaReconcile extends Command
{
    protected $signature = 'pos:mpesa-reconcile';
    protected $description = 'Check unanswered M-Pesa prompts with Kopo Kopo and close paid bills';

    public function handle(KopoKopo $kk, PaymentSettler $settler): int
    {
        if (! KopoKopo::ready()) return self::SUCCESS;
        $pending = StkRequest::with('ticket.items')->where('status', 'pending')->whereNotNull('location')
            ->where('created_at', '<=', now()->subMinute())->where('created_at', '>=', now()->subHours(12))->limit(25)->get();
        if ($pending->isEmpty()) { $this->line('No unanswered prompts.'); return self::SUCCESS; }
        $token = $kk->token();
        foreach ($pending as $stk) {
            try {
                $r = Http::withToken($token)->acceptJson()->timeout(15)->get($stk->location);
                if (! $r->successful()) { $this->warn("#{$stk->id}: Kopo Kopo said {$r->status()}"); continue; }
                $attr = $r->json('data.attributes') ?? [];
                $status = (string) ($attr['status'] ?? '');
                $res = $attr['event']['resource'] ?? null;
                $ref = is_array($res) ? ($res['reference'] ?? null) : null;
                if ($status === 'Success' && $ref) {
                    $stk->update(['status' => 'success']);
                    if (! Payment::where('kk_reference', $ref)->exists() && $stk->ticket && $stk->ticket->status !== 'paid') {
                        $settler->settle($stk->ticket, 'kk_stk', (float) $res['amount'], $ref, null);
                        KkEvent::where('reference', $ref)->update(['status' => 'matched']);
                        $this->info("#{$stk->id}: paid {$ref}, bill {$stk->ticket->order_no} closed (webhook was missing)");
                    }
                } elseif ($status === 'Failed') {
                    $errors = (array) ($attr['event']['errors'] ?? []);
                    $stk->update(['status' => 'failed', 'error' => mb_substr(implode('; ', $errors) ?: 'Payment was not completed.', 0, 300)]);
                    $this->line("#{$stk->id}: not paid");
                } elseif ($stk->created_at->lt(now()->subMinutes(30))) {
                    $stk->update(['status' => 'failed', 'error' => 'No answer from the customer.']);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return self::SUCCESS;
    }
}
