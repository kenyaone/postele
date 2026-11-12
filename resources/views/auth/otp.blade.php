<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('pos.cafe_name') }} POS &mdash; Enter code</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        <h1 class="text-2xl font-bold text-center">Check WhatsApp</h1>
        <p class="text-center text-gray-500 mb-6">
            We sent a 6-digit code to {{ $name }}&rsquo;s WhatsApp ({{ $masked }}).
        </p>

        @if (session('ok'))
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded p-3 mb-4 text-sm">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.otp.verify') }}">
            @csrf
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required
                   class="w-full border rounded-lg px-3 py-2 mb-4 text-center text-3xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <label class="flex items-center gap-2 text-sm text-gray-600 mb-6">
                <input type="checkbox" name="remember_device" value="1" checked>
                Trust this device for 30 days
            </label>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg py-3">
                Verify &amp; sign in
            </button>
        </form>

        <div class="flex justify-between mt-4 text-sm">
            <form method="POST" action="{{ route('login.otp.resend') }}">@csrf
                <button class="text-emerald-700 hover:underline">Resend code</button>
            </form>
            <a href="{{ route('login') }}" class="text-gray-500 hover:underline">Start over</a>
        </div>
    </div>
</body>
</html>
