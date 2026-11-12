<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Open shift &mdash; {{ config('pos.cafe_name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
@include('partials.pwa')
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        <h1 class="text-2xl font-bold mb-2">Open shift</h1>
        <p class="text-gray-600 mb-6">Cashier: <strong>{{ auth()->user()->name }}</strong></p>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('pos.shift.open') }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">Cash in till at opening (KSh)</label>
            <input type="number" name="cash_opening" data-keypad="money" data-keypad-label="Cash in till" step="0.01" min="0" required autofocus
                   value="{{ old('cash_opening', '0') }}"
                   class="w-full border rounded-lg px-3 py-2 mb-6 text-2xl text-right focus:ring-2 focus:ring-emerald-500">

            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg py-3">
                Open shift &amp; start
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button class="text-red-600 text-sm hover:underline">Logout</button>
        </form>
    </div>
@include('partials.touch')
@include('partials.footer')
</body>
</html>
