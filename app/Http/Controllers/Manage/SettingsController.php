<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsAppOtpSender;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('manage.settings', [
            'values' => [
                'cafe_name'    => config('pos.cafe_name'),
                'till_number'  => config('pos.till_number'),
                'phone'        => config('pos.phone'),
                'thank_you_wa' => config('pos.thank_you_wa'),
            ],
            'myWhatsapp' => $request->user()->whatsapp_phone,
            'otpEnabled' => Setting::where('key', 'otp_enabled')->value('value') === '1',
            'otpReady'   => WhatsAppOtpSender::ready(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cafe_name'      => 'required|string|max:40',
            'till_number'    => ['required', 'string', 'regex:/^[0-9]{4,10}$/'],
            'phone'          => 'nullable|string|max:30',
            'thank_you_wa'   => 'nullable|string|max:60',
            'whatsapp_phone' => 'nullable|string|max:20',
            'otp_enabled'    => 'nullable|boolean',
        ], [
            'till_number.regex' => 'Till number must be 4 to 10 digits, numbers only.',
        ]);

        $myPhone = null;
        if (! empty($data['whatsapp_phone'])) {
            $myPhone = WhatsAppOtpSender::normalize($data['whatsapp_phone']);
            if (! $myPhone) {
                return back()->withInput()->withErrors(['whatsapp_phone' => 'Enter a Kenyan mobile number, e.g. 0712 345 678.']);
            }
        }

        $wantOtp = (bool) ($data['otp_enabled'] ?? false);
        if ($wantOtp && ! WhatsAppOtpSender::ready()) {
            return back()->withInput()->withErrors(['otp_enabled' => 'WhatsApp codes cannot be switched on until the Africa\'s Talking details are added to .env.']);
        }
        if ($wantOtp && ! $myPhone) {
            return back()->withInput()->withErrors(['otp_enabled' => 'Add your WhatsApp number first, or you could lock yourself out.']);
        }

        foreach (Setting::EDITABLE as $key) {
            Setting::put($key, isset($data[$key]) ? trim($data[$key]) : null);
        }
        Setting::put('otp_enabled', $wantOtp ? '1' : '0');
        $request->user()->update(['whatsapp_phone' => $myPhone]);

        return redirect()->route('manage.settings')->with('ok', 'Settings saved.');
    }
}
