@extends('layouts.manage')
@section('title', 'Change my PIN')

@section('content')
<div class="max-w-md">
    @if ($usesPassword)
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5">
            <p class="text-sm">You sign in with a manager password, not a PIN. Change it in <a href="{{ route('manage.settings') }}" class="font-semibold underline">Settings</a>.</p>
        </div>
    @else
        <form method="POST" action="{{ route('pos.pin.update') }}" class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5 space-y-4">
            @csrf
            <p class="text-sm text-gray-600">Signed in as <b>{{ auth()->user()->name }}</b>. Your PIN is private: never share it, so nobody else can sell or cancel orders in your name.</p>
            @if ($errors->any())<div class="rounded-xl bg-red-50 text-red-800 text-sm font-semibold p-3">{{ $errors->first() }}</div>@endif
            <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">Current PIN</span>
                <input type="password" name="current" required inputmode="numeric" autocomplete="current-password" maxlength="20" data-keypad="pin" data-keypad-label="Current PIN" class="mt-1 w-full border rounded-xl px-3 py-3 text-2xl tracking-[0.4em]"></label>
            <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">New PIN (4 to 6 numbers)</span>
                <input type="password" name="pin" required inputmode="numeric" autocomplete="new-password" maxlength="6" data-keypad="pin" data-keypad-label="New PIN" class="mt-1 w-full border rounded-xl px-3 py-3 text-2xl tracking-[0.4em]"></label>
            <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">New PIN again</span>
                <input type="password" name="pin_confirmation" required inputmode="numeric" autocomplete="new-password" maxlength="6" data-keypad="pin" data-keypad-label="New PIN again" class="mt-1 w-full border rounded-xl px-3 py-3 text-2xl tracking-[0.4em]"></label>
            <button class="w-full rounded-2xl bg-espresso hover:bg-roast text-crema font-extrabold py-3">Change my PIN</button>
            <p class="text-xs text-gray-500">Forgot your PIN? Ask the manager to set a new one under Users.</p>
        </form>
    @endif
</div>
@include('partials.touch')
@endsection
