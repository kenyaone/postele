<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#2b1b14"><meta name="robots" content="noindex">
<title>Pay bill {{ $t->order_no }} - {{ config('pos.cafe_name') }}</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<style>body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif}</style>
</head>
<body class="min-h-screen bg-[#f7efe4] text-[#2b1b14]">
<div class="max-w-md mx-auto p-4">
    <div class="rounded-3xl bg-[#2b1b14] text-[#f7efe4] p-5 text-center">
        <img src="{{ config('pos.logo') ?: '/icons/icon-192.png' }}" alt="" class="w-14 h-14 mx-auto rounded-2xl">
        <div class="mt-2 text-xl font-extrabold">{{ config('pos.cafe_name') }}</div>@if (config('pos.address'))<div class="text-xs opacity-80">{{ config('pos.address') }}</div>@endif
        <div class="text-sm opacity-80">Bill {{ $t->order_no }}</div>
    </div>

    <div class="mt-4 rounded-3xl bg-white shadow p-5">
        <ul class="divide-y text-sm">
            @foreach ($t->items as $l)
                <li class="py-2 flex justify-between gap-3"><span>{{ $l->qty }} x {{ \Illuminate\Support\Str::after($l->item->name ?? '', 'Glovo: ') }}</span><span class="tabular-nums">{{ number_format($l->qty * $l->unit_price) }}</span></li>
            @endforeach
        </ul>
        <div class="mt-3 pt-3 border-t-2 border-dashed flex justify-between items-end"><span class="font-semibold">Total</span><span class="text-3xl font-extrabold">KSh {{ number_format(ceil($t->total)) }}</span></div>
    </div>

    <div id="state" class="mt-4">
        @if ($t->status === 'paid')
            <div class="rounded-3xl bg-emerald-600 text-white p-6 text-center"><div class="text-5xl">&#10003;</div><div class="mt-2 text-xl font-extrabold">Paid. Thank you!</div></div>
        @elseif ($t->status === 'void')
            <div class="rounded-3xl bg-gray-200 p-6 text-center font-semibold">This bill was cancelled. Please ask the waiter.</div>
        @else
            @if (session('sent'))
                <div id="waiting" class="rounded-3xl bg-amber-100 ring-2 ring-amber-300 p-5 text-center">
                    <div class="text-3xl">&#128241;</div>
                    <div class="mt-1 text-lg font-extrabold">Check your phone</div>
                    <p class="text-sm">Enter your M-Pesa PIN on the prompt sent to {{ session('sent') }}. This page updates by itself.</p>
                </div>
            @endif
            <form method="POST" action="{{ route('pay.send', $code) }}" class="rounded-3xl bg-white shadow p-5 space-y-3 {{ session('sent') ? 'mt-3' : '' }}">
                @csrf
                <label class="block text-sm font-semibold">Your M-Pesa number
                    <input name="phone" type="tel" inputmode="numeric" autocomplete="tel" required value="{{ old('phone') }}" placeholder="0712 345 678" class="mt-1 w-full rounded-2xl border-2 border-[#2b1b14]/15 px-4 py-4 text-xl tracking-wider focus:border-[#f5a524] focus:outline-none">
                </label>
                @error('phone')<p class="text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                <button class="w-full rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-extrabold py-4" @disabled(! $ready)>{{ session('sent') ? 'Send the prompt again' : 'Pay KSh ' . number_format(ceil($t->total)) . ' with M-Pesa' }}</button>
                <p class="text-xs text-center text-gray-500">You will get an M-Pesa prompt. Enter your PIN and this bill is marked paid. Or pay Till {{ config('pos.till_number') }} and show the waiter your message.</p>
            </form>
        @endif
    </div>
    <p class="mt-6 text-center text-xs text-gray-500">{{ config('pos.cafe_name') }} &middot; Developed by MTTI Eldoret</p>
</div>
@if (in_array($t->status, ['open', 'sent'], true))
<script>
    (function () {
        var n = 0, sent = {{ session('sent') ? 'true' : 'false' }};
        function poll() {
            fetch(@json(route('pay.status', $code)), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
                if (d.paid) { document.getElementById('state').innerHTML = '<div class="rounded-3xl bg-emerald-600 text-white p-6 text-center"><div class="text-5xl">&#10003;</div><div class="mt-2 text-xl font-extrabold">Paid. Thank you!</div></div>'; return; }
                var w = document.getElementById('waiting');
                if (w && d.prompt === 'failed') { w.className = 'rounded-3xl bg-red-50 ring-2 ring-red-200 p-5 text-center'; w.innerHTML = '<div class="font-extrabold">Not paid</div><p class="text-sm">' + (d.error || 'The payment did not go through.') + ' You can try again below.</p>'; return; }
                if (++n < (sent ? 60 : 20)) setTimeout(poll, sent ? 3000 : 6000);
            }).catch(function () { if (++n < 60) setTimeout(poll, 5000); });
        }
        poll();
    })();
</script>
@endif
</body>
</html>
