<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends login codes over WhatsApp.
 *  POS_OTP_DRIVER=log            -> writes the code to storage/logs (testing only)
 *  POS_OTP_DRIVER=africastalking -> Africa's Talking WhatsApp, using an approved template
 */
class WhatsAppOtpSender
{
    private const AT_KEYS = ['at_username', 'at_api_key', 'at_wa_number', 'at_wa_template', 'at_wa_url'];

    /** True only when a real WhatsApp provider is fully configured. */
    public static function ready(): bool
    {
        if (config('pos.otp_driver') !== 'africastalking') return false;
        foreach (self::AT_KEYS as $k) {
            if (! config("pos.$k")) return false;
        }
        return true;
    }

    public function send(string $phone, string $code): void
    {
        $driver = config('pos.otp_driver');

        if ($driver === 'log') {
            Log::info("[OTP log driver] code for $phone: $code");
            return;
        }

        if ($driver !== 'africastalking') {
            throw new RuntimeException("Unknown OTP driver: $driver");
        }
        if (! self::ready()) {
            throw new RuntimeException('Africa\'s Talking WhatsApp is not fully configured in .env.');
        }

        $res = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['apiKey' => config('pos.at_api_key')])
            ->post(config('pos.at_wa_url'), [
                'username'    => config('pos.at_username'),
                'waNumber'    => config('pos.at_wa_number'),
                'phoneNumber' => $phone,
                'body'        => [
                    'templateId' => config('pos.at_wa_template'),
                    'bodyValues' => [$code],
                ],
            ]);

        if (! $res->successful()) {
            Log::warning('WhatsApp OTP send failed', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            throw new RuntimeException('WhatsApp send failed (HTTP ' . $res->status() . ').');
        }
    }

    /** Kenyan numbers to +254 format; returns null if not a valid mobile number. */
    public static function normalize(?string $raw): ?string
    {
        $d = preg_replace('/[^0-9]/', '', (string) $raw);
        if (preg_match('/^(?:254|0)?([17][0-9]{8})$/', $d, $m)) {
            return '+254' . $m[1];
        }
        return null;
    }

    public static function mask(?string $phone): string
    {
        $p = (string) $phone;
        return strlen($p) > 8 ? substr($p, 0, 5) . str_repeat('*', strlen($p) - 8) . substr($p, -3) : '***';
    }
}
