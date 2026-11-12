@extends('layouts.manage')
@section('title', 'Settings')

@section('content')
<div class="max-w-xl">

        <form method="POST" action="{{ route('manage.settings.update') }}" class="bg-white rounded-xl shadow p-6 space-y-4">
            @csrf @method('PATCH')
            <p class="text-sm text-gray-500">These print on bills, receipts and X/Z reports.</p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">M-Pesa Till number</label>
                <input name="till_number" inputmode="numeric" required value="{{ old('till_number', $values['till_number'] === 'TBD' ? '' : $values['till_number']) }}"
                       placeholder="e.g. 5123456" class="w-full border rounded px-3 py-2 text-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Caf&eacute; name (top of receipt)</label>
                <input name="cafe_name" required maxlength="40" value="{{ old('cafe_name', $values['cafe_name']) }}"
                       class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input name="phone" maxlength="30" value="{{ old('phone', $values['phone']) }}"
                       class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp link (receipt footer)</label>
                <input name="thank_you_wa" maxlength="60" value="{{ old('thank_you_wa', $values['thank_you_wa']) }}"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div class="border-t pt-4 mt-2">
                <h2 class="font-semibold mb-1">Manager login security</h2>
                <p class="text-sm text-gray-500 mb-3">When on, managers get a 6-digit code on WhatsApp after their PIN (once per device every 30 days). Cashiers and waiters are not affected.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Your WhatsApp number</label>
                <input name="whatsapp_phone" inputmode="tel" value="{{ old('whatsapp_phone', $myWhatsapp) }}" placeholder="e.g. 0712 345 678"
                       class="w-full border rounded px-3 py-2 mb-3">
                <label class="flex items-center gap-2 text-sm {{ $otpReady ? '' : 'text-gray-400' }}">
                    <input type="hidden" name="otp_enabled" value="0">
                    <input type="checkbox" name="otp_enabled" value="1" {{ old('otp_enabled', $otpEnabled) ? 'checked' : '' }} {{ $otpReady ? '' : 'disabled' }}>
                    Require a WhatsApp code for manager logins
                </label>
                @unless ($otpReady)
                    <p class="text-xs text-amber-700 mt-1">Not available yet: the Africa&rsquo;s Talking WhatsApp details still need to be added to the server&rsquo;s .env file.</p>
                @endunless
            </div>
            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">Save settings</button>
        </form>
</div>
@endsection
