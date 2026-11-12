<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsAppOtpSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PinLoginController extends Controller
{
    private const OTP_TTL      = 300;   // code valid 5 minutes
    private const OTP_ATTEMPTS = 5;
    private const RESEND_AFTER = 60;
    private const TRUST_DAYS   = 30;

    public function show()
    {
        $users = User::where('active', true)
            ->orderByRaw("FIELD(role, 'manager', 'cashier', 'waiter')")
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('auth.pin-login', compact('users'));
    }

    public function store(Request $request, WhatsAppOtpSender $otp)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'pin'     => 'required|string|min:4|max:10',
        ]);

        $user = User::find($data['user_id']);

        if (! $user || ! $user->active || ! $user->pin_hash || ! Hash::check($data['pin'], $user->pin_hash)) {
            return back()
                ->withInput($request->only('user_id'))
                ->withErrors(['pin' => 'Invalid PIN.']);
        }

        if ($this->needsOtp($request, $user)) {
            try {
                $this->issueOtp($request, $user, $otp);
            } catch (\Throwable $e) {
                report($e);
                return back()->withInput($request->only('user_id'))
                    ->withErrors(['pin' => 'Could not send the WhatsApp code. Try again in a moment.']);
            }
            return redirect()->route('login.otp');
        }

        return $this->finishLogin($request, $user);
    }

    public function showOtp(Request $request)
    {
        $pending = $request->session()->get('otp');
        if (! $pending) return redirect()->route('login');

        $user = User::find($pending['user_id']);
        return view('auth.otp', [
            'name'   => $user?->name,
            'masked' => WhatsAppOtpSender::mask($user?->whatsapp_phone),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $pending = $request->session()->get('otp');

        if (! $pending) {
            return redirect()->route('login')->withErrors(['pin' => 'Your sign-in expired. Enter your PIN again.']);
        }
        if (time() > $pending['expires'] || $pending['attempts'] >= self::OTP_ATTEMPTS) {
            $request->session()->forget('otp');
            return redirect()->route('login')->withErrors(['pin' => 'That code has expired. Enter your PIN again.']);
        }
        if (! Hash::check($data['code'], $pending['hash'])) {
            $pending['attempts']++;
            $request->session()->put('otp', $pending);
            $left = self::OTP_ATTEMPTS - $pending['attempts'];
            return back()->withErrors(['code' => "Wrong code. $left " . ($left === 1 ? 'try' : 'tries') . ' left.']);
        }

        $request->session()->forget('otp');
        $user = User::find($pending['user_id']);
        if (! $user || ! $user->active) return redirect()->route('login');

        if ($request->boolean('remember_device')) {
            Cookie::queue('pos_td', $user->id . '|' . (time() + self::TRUST_DAYS * 86400), self::TRUST_DAYS * 24 * 60);
        }

        return $this->finishLogin($request, $user);
    }

    public function resendOtp(Request $request, WhatsAppOtpSender $otp)
    {
        $pending = $request->session()->get('otp');
        if (! $pending) return redirect()->route('login');

        if (time() - $pending['sent_at'] < self::RESEND_AFTER) {
            return back()->withErrors(['code' => 'Please wait a minute before asking for another code.']);
        }

        try {
            $this->issueOtp($request, User::findOrFail($pending['user_id']), $otp);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['code' => 'Could not send the WhatsApp code. Try again in a moment.']);
        }
        return back()->with('ok', 'A new code has been sent.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function needsOtp(Request $request, User $user): bool
    {
        if ($user->role !== 'manager') return false;
        if (Setting::where('key', 'otp_enabled')->value('value') !== '1') return false;

        $trusted = (string) $request->cookie('pos_td');
        if ($trusted !== '') {
            [$uid, $exp] = array_pad(explode('|', $trusted), 2, 0);
            if ((int) $uid === $user->id && (int) $exp > time()) return false;
        }
        return true;
    }

    private function issueOtp(Request $request, User $user, WhatsAppOtpSender $otp): void
    {
        if (! $user->whatsapp_phone) {
            throw new RuntimeException("User {$user->id} has no WhatsApp number.");
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp->send($user->whatsapp_phone, $code);   // only remember the code once it was actually sent

        $request->session()->put('otp', [
            'user_id'  => $user->id,
            'hash'     => Hash::make($code),
            'expires'  => time() + self::OTP_TTL,
            'attempts' => 0,
            'sent_at'  => time(),
        ]);
    }

    private function finishLogin(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
