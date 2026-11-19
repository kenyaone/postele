<?php

namespace App\Console\Commands;

use App\Models\KkEvent;
use App\Models\Payment;
use App\Services\KopoKopo;
use App\Services\TillAutoMatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Safety net for till payments whose Kopo Kopo notice never reached the server (server down, internet cut).
 * Asks Kopo Kopo for every till payment in the time window and adds any we do not have, as an unlinked
 * payment: it then shows on "Clear my bills" and waiters clear it with the M-Pesa code as usual.
 */
class KkPoll extends Command
{
    protected $signature = 'pos:kk-poll {--hours=3 : How far back to look} {--dry : Only report what is missing}';
    protected $description = 'Fetch till payments from Kopo Kopo and add any the server missed';

    public function handle(KopoKopo $kk, TillAutoMatcher $matcher): int
    {
        if (! KopoKopo::ready()) { $this->line('Kopo Kopo is not set up.'); return self::SUCCESS; }
        $tills = array_filter([(string) config('pos.kk_till'), (string) KopoKopo::stkTill()]);
        $base = rtrim((string) config('pos.kk_base_url'), '/');
        $hours = max(1, min(72, (int) $this->option('hours')));
        $token = $kk->token();

        $r = Http::withToken($token)->acceptJson()->timeout(20)->post($base . '/api/v2/polling', [
            'scope' => 'company', 'scope_reference' => '',
            'from_time' => now()->subHours($hours)->toIso8601String(), 'to_time' => now()->toIso8601String(),
            '_links' => ['callback_url' => route('kk.poll.callback')],
        ]);
        $location = $r->header('Location');
        if ($r->status() !== 201 || ! $location) {
            $this->error('Polling request failed (HTTP ' . $r->status() . '): ' . mb_substr($r->body(), 0, 200));
            return self::FAILURE;
        }

        // Kopo Kopo prepares the list in the background; ask for it until it is ready (about a minute at most).
        $attr = [];
        for ($i = 0; $i < 12; $i++) {
            sleep($i ? 5 : 3);
            $g = Http::withToken($token)->acceptJson()->timeout(20)->get($location);
            $attr = $g->json('data.attributes') ?? [];
            if (in_array($attr['status'] ?? '', ['Success', 'Failed'], true)) break;
        }
        if (($attr['status'] ?? '') !== 'Success') {
            $this->error('Kopo Kopo did not return the list (status: ' . ($attr['status'] ?? 'none') . ').');
            return self::FAILURE;
        }

        $seen = 0; $added = 0; $linked = 0;
        foreach ((array) ($attr['transactions'] ?? []) as $tx) {
            $res = $tx['resource'] ?? [];
            $ref = $res['reference'] ?? null;
            if (! $ref || ! in_array((string) ($res['till_number'] ?? ''), $tills, true)) continue;   // other MTTI tills
            if (strtolower((string) ($res['status'] ?? 'received')) !== 'received') continue;
            $seen++;
            if (KkEvent::where('reference', $ref)->exists() || Payment::where('kk_reference', $ref)->exists()) continue;
            if ($this->option('dry')) { $added++; $this->info("Missing: {$ref} KSh " . (float) ($res['amount'] ?? 0) . ' at ' . ($res['origination_time'] ?? '?')); continue; }

            $event = KkEvent::create([
                'raw_payload' => ['topic' => 'buygoods_transaction_received', 'source' => 'polling', 'event' => ['resource' => $res]],
                'event_type'  => 'buygoods_transaction_received',
                'amount'      => $res['amount'] ?? null,
                'reference'   => $ref,
                'received_at' => isset($res['origination_time']) ? Carbon::parse($res['origination_time'])->setTimezone(config('app.timezone')) : now(),
                'status'      => 'pending',
            ]);
            $added++;
            try { if ($matcher->match($event)) $linked++; } catch (\Throwable $e) { report($e); }
            $this->info("Added missed payment {$ref} (KSh " . (float) ($res['amount'] ?? 0) . ')');
        }
        $this->line("Checked {$seen} till payment(s) in the last {$hours}h: {$added} were missing and added" . ($linked ? ", {$linked} linked to a bill" : '') . '.');
        return self::SUCCESS;
    }
}
