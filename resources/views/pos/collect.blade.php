<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collect M-Pesa  Tele Caf</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { espresso: '#2b1b14', crema: '#f7efe4', roast: '#6b3f26', saffron: '#f5a524' } } } };</script>
    @if ($waiting)<meta http-equiv="refresh" content="8">@endif
</head>
<body class="bg-crema text-espresso min-h-screen antialiased">
<header class="bg-espresso text-crema sticky top-0 z-10 px-4 py-3 flex items-center justify-between shadow">
    <div><div class="font-bold"> &#128242; Collect M-Pesa</div><div class="text-xs opacity-70">{{ $me->name }} - {{ $bills->count() }} unpaid</div></div>
    <div class="flex gap-2">
        <a href="{{ route('pos.collect') }}" class="text-sm rounded-lg bg-white/10 px-3 py-2">Refresh</a>
        <a href="{{ route('pos.index') }}" class="text-sm rounded-lg bg-white/10 px-3 py-2">POS</a>
    </div>
</header>
<main class="p-3 space-y-3 max-w-md mx-auto pb-16">
    @if (session('ok'))<div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-300 p-3 text-sm">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-red-50 ring-1 ring-red-300 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    @forelse ($bills as $t)
        @php $s = $prompts->get($t->id); @endphp
        <div class="rounded-2xl bg-white ring-1 ring-roast/15 p-4 shadow-sm">
            <div class="flex justify-between items-start gap-3">
                <div class="min-w-0">
                    <div class="font-mono font-bold">{{ $t->order_no }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $t->items->map(fn ($l) => optional($l->item)->name)->filter()->implode(', ') }}</div>
                    <div class="text-xs text-gray-400">{{ $t->created_at->format('D H:i') }}@if ($me->role === 'manager')  {{ optional($t->opener)->name }}@endif</div>
                </div>
                <div class="text-right shrink-0"><div class="text-xs text-gray-500">To pay</div><div class="text-xl font-extrabold">KSh {{ number_format($t->balance) }}</div></div>
            </div>

            @if ($s && $s->phone === 'kk-app' && $s->status === 'pending')
                <div class="mt-2 text-xs rounded-lg bg-amber-50 text-amber-800 px-2 py-1">Waiting for a Kopo Kopo app payment</div>
            @elseif ($s && in_array($s->status, ['pending', 'sent'], true))
                <div class="mt-2 text-xs rounded-lg bg-amber-50 text-amber-800 px-2 py-1 animate-pulse">Prompt sent to {{ substr($s->phone, 0, 6) }}***{{ substr($s->phone, -3) }}  waiting for the customer's PIN</div>
            @elseif ($s && $s->status === 'failed')
                <div class="mt-2 text-xs rounded-lg bg-red-50 text-red-700 px-2 py-1">Last prompt not paid: {{ $s->error ?: 'cancelled or timed out' }}. Send again.</div>
            @endif

            <form method="POST" action="{{ route('pos.tickets.mpesa.stk', $t) }}" class="mt-3 flex gap-2" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Sending'">
                @csrf
                <input type="hidden" name="from" value="collect">
                <input type="tel" name="phone" inputmode="numeric" autocomplete="off" required placeholder="Customer phone 07" class="flex-1 min-w-0 rounded-xl ring-1 ring-roast/30 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-saffron">
                <button class="rounded-xl bg-saffron px-4 py-3 font-bold disabled:opacity-60">Send</button>
            </form>
        </div>
    @empty
        <p class="text-center text-gray-500 py-16">No unpaid bills </p>
    @endforelse
</main>
</body>
</html>
