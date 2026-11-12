<?php

namespace App\Services;

use App\Models\StkRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kopo Kopo (K2 Connect) client. All credentials come from .env via config('pos.kk_*').
 * Docs: developers.kopokopo.com (STK: POST /api/v2/incoming_payments; webhooks signed with X-KopoKopo-Signature).
 */
class KopoKopo
{
    public static function ready(): bool
    {
        foreach (['kk_client_id', 'kk_client_secret', 'kk_api_key', 'kk_base_url'] as $k) {
            if (! config("pos.$k")) return false;
        }
        return (bool) self::stkTill();
    }

    public static function stkTill(): ?string
    {
        return config('pos.kk_stk_till') ?: config('pos.kk_till');
    }

    private function base(): string
    {
        return rtrim((string) config('pos.kk_base_url'), '/');
    }

    public function token(): string
    {
        return Cache::remember('kk_access_token', 3000, function () {
            $r = Http::asForm()->acceptJson()->timeout(15)->post($this->base() . '/oauth/token', [
                'client_id'     => config('pos.kk_client_id'),
                'client_secret' => config('pos.kk_client_secret'),
                'grant_type'    => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('Kopo Kopo token request failed (HTTP ' . $r->status() . ').');
            }
            return $r->json('access_token');
        });
    }

    /** Sends the M-Pesa prompt. Returns the Kopo Kopo Location URL of the incoming payment. */
    public function stkPush(StkRequest $stk): string
    {
        $r = Http::withToken($this->token())->acceptJson()->timeout(20)->post($this->base() . '/api/v2/incoming_payments', [
            'payment_channel' => 'M-PESA STK Push',
            'till_number'     => self::stkTill(),
            'subscriber'      => ['first_name' => 'Tele Cafe', 'last_name' => 'Customer', 'phone_number' => $stk->phone],
            'amount'          => ['currency' => 'KES', 'value' => (int) $stk->amount],
            'metadata'        => ['ticket_id' => (string) $stk->ticket_id, 'stk_request_id' => (string) $stk->id],
            '_links'          => ['callback_url' => route('kk.stk.callback')],
        ]);

        if ($r->status() === 401) Cache::forget('kk_access_token');
        $location = $r->header('Location');
        if ($r->status() !== 201 || ! $location) {
            throw new RuntimeException('STK request failed (HTTP ' . $r->status() . '): ' . mb_substr($r->body(), 0, 300));
        }
        return $location;
    }

    /** HMAC-SHA256 of the raw body. Docs mention both the API key and the client secret, so accept either. */
    public static function verify(string $body, ?string $signature): bool
    {
        if (! $signature) return false;
        foreach ([config('pos.kk_api_key'), config('pos.kk_client_secret')] as $key) {
            if ($key && hash_equals(hash_hmac('sha256', $body, $key), $signature)) return true;
        }
        return false;
    }

    /** Kenyan mobile numbers to +2547XXXXXXXX / +2541XXXXXXXX; null if not valid. */
    public static function normalizePhone(?string $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        return preg_match('/^(?:254|0)?([17]\d{8})$/', $d, $m) ? '+254' . $m[1] : null;
    }
}
