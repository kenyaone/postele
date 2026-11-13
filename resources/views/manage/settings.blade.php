@extends('layouts.manage')
@section('title', 'Settings')

@section('content')
<div class="max-w-xl">

        <form method="POST" action="{{ route('manage.settings.update') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 space-y-4">
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

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location / address (on bills and the pay page)</label>
                <input name="address" maxlength="80" value="{{ old('address', config('pos.address')) }}" placeholder="e.g. Sagaas Centre, Eldoret" class="w-full border rounded px-3 py-2">
            </div>
            <div class="rounded-xl bg-gray-50 p-3 space-y-2">
                <label class="block text-sm font-medium text-gray-700">Cafe logo</label>
                <div class="flex items-center gap-3">
                    @if (config('pos.logo'))<img src="{{ config('pos.logo') }}" alt="" class="w-16 h-16 rounded-2xl object-cover bg-white shadow">@else<span class="w-16 h-16 rounded-2xl bg-saffron grid place-items-center text-2xl">&#9749;</span>@endif
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="text-sm">
                </div>
                @error('logo')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-500">PNG or JPG, square works best. Shown on the POS, sign-in, bills on screen and the customer pay page.</p>
                @if (config('pos.logo'))<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1"> Remove the logo</label>@endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="print_logo" value="1" @checked(old('print_logo', config('pos.print_logo')))> Also print the logo on thermal bills (uses a little more paper)</label>
            </div>

            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">Save settings</button>
        </form>

        <form method="POST" action="{{ route('manage.password.update') }}" class="bg-white rounded-xl shadow p-6 space-y-4 mt-6">
            @csrf @method('PATCH')
            <h2 class="font-semibold">Your sign-in password</h2>
            <p class="text-sm text-gray-500">
                @if ($hasPassword)
                    You sign in with a password. Change it here.
                @else
                    You are still signing in with your PIN. Set a password (8+ characters, letters and numbers); after that only the password works for your account. Cashiers and waiters keep their PINs.
                @endif
            </p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $hasPassword ? 'Current password' : 'Current PIN' }}</label>
                <input type="password" name="current" required autocomplete="current-password" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                <input type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Repeat new password</label>
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="w-full border rounded px-3 py-2">
            </div>
            <button class="w-full bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded py-3">{{ $hasPassword ? 'Change password' : 'Set password' }}</button>
        </form>
</div>
@endsection
