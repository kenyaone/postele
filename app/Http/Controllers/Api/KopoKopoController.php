<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\StkRequest;
use App\Services\KopoKopo;
use App\Services\PaymentSettler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Endpoints Kopo Kopo calls. Every request must carry a valid X-KopoKopo-Signature. */
class KopoKopoController extends Controller
{
    /** buygoods_transaction_received (and any other subscribed topic). */
    public function webhook(Request $request)
    {
        $body = $request->getContent();
        if (! KopoKopo::verify($body, $request->header('X-KopoKopo-Signature'))) {
            Log::warning('Kopo Kopo webhook rejected: bad or missing signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $p     = json_decode($body, true) ?: [];
        $topic = (string) ($p['topic'] ?? 'unknown');
        $res   = $p['event']['resource'] ?? [];
        $ref   = $res['reference'] ?? null;

        if ($ref && KkEvent::where('reference', $ref)->where('event_type', $topic)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $status = 'pending';
        if ($topic !== 'buygoods_transaction_received') $status = 'ignored';
        elseif ($ref && Payment::where('kk_reference', $ref)->exists()) $status = 'matched';   // already paid via STK

        $tillEvent = KkEvent::create([
            'raw_payload' => $p,
            'event_type'  => $topic,
            'amount'      => $res['amount'] ?? null,
            'reference'   => $ref,
            'received_at' => isset($res['origination_time']) ? Carbon::parse($res['origination_time'])->setTimezone(config('app.timezone')) : now(),
            'status'      => $status,
        ]);

        // Paid the till directly: link it to the one unpaid order with the same amount, if there is exactly one.
        if ($status === 'pending') {
            try { app(\App\Services\TillAutoMatcher::class)->match($tillEvent); } catch (\Throwable $e) { report($e); }
        }

        return response()->json(['ok' => true]);
    }

    /** Result of an STK prompt we sent. */
    public function stkCallback(Request $request, PaymentSettler $settler)
    {
        $body = $request->getContent();
        if (! KopoKopo::verify($body, $request->header('X-KopoKopo-Signature'))) {
            Log::warning('Kopo Kopo STK callback rejected: bad or missing signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $p    = json_decode($body, true) ?: [];
        $data = $p['data'] ?? [];
        $attr = $data['attributes'] ?? [];
        $res  = $attr['event']['resource'] ?? null;
        $ref  = $res['reference'] ?? null;

        $stk = StkRequest::where('kk_id', (string) ($data['id'] ?? '-'))->first()
            ?? StkRequest::find((int) ($attr['metadata']['stk_request_id'] ?? 0));

        if ($ref && KkEvent::where('reference', $ref)->where('event_type', 'stk_result')->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }
        $event = KkEvent::create([
            'raw_payload' => $p, 'event_type' => 'stk_result', 'amount' => $res['amount'] ?? null,
            'reference' => $ref, 'received_at' => now(), 'status' => 'pending',
        ]);

        if (($attr['status'] ?? '') !== 'Success' || ! $res || ! $ref) {
            $errors = (array) ($attr['event']['errors'] ?? []);
            $stk?->update(['status' => 'failed', 'error' => mb_substr(implode('; ', $errors) ?: 'Payment was not completed.', 0, 300)]);
            $event->update(['status' => 'ignored']);
            return response()->json(['ok' => true]);
        }

        $stk?->update(['status' => 'success']);
        if (! $stk) return response()->json(['ok' => true]);   // unknown request: stays pending for the manager

        try {
            $settler->settle($stk->ticket, 'kk_stk', (float) $res['amount'], $ref, null);
            KkEvent::where('reference', $ref)->update(['status' => 'matched']);
        } catch (\Throwable $e) {
            report($e);   // e.g. ticket already paid another way: event stays pending for review
        }
        return response()->json(['ok' => true]);
    }
}
