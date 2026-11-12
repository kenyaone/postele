@php
    $me = auth()->user();
    $no = $ticket ? $ticket->order_no : null;
    $openBills = \App\Models\Ticket::whereIn('status', ['open', 'sent'])->count();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ticket ? 'Order #' . $no . ' - ' : '' }}{{ config('pos.cafe_name') }} POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { display: ['Fraunces', 'Georgia', 'serif'], sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
            colors: { espresso: '#2b1b14', crema: '#f7efe4', roast: '#6b3f26', saffron: '#f5a524' }
        } } };
    </script>
    <style>
        body { background-color: #f7efe4; background-image: radial-gradient(rgba(107, 63, 38, .07) 1px, transparent 1px); background-size: 18px 18px; }
        .tile { transition: transform .08s ease, box-shadow .15s ease; }
        .tile:not(:disabled):active { transform: scale(.95); }
        @keyframes pop { from { transform: scale(.85); opacity: .3; } to { transform: scale(1); opacity: 1; } }
        .pop { animation: pop .25s ease-out; }
        #cat-chips::-webkit-scrollbar { display: none; }
    </style>
@include('partials.pwa')
</head>
<body class="font-sans text-espresso min-h-screen antialiased">
<header class="bg-espresso text-crema sticky top-0 z-30 shadow-lg">
    <div class="px-4 py-2.5 flex items-center gap-3">
        <a href="{{ route('pos.index') }}" class="flex items-center gap-2.5">
            <span class="w-10 h-10 rounded-2xl bg-saffron text-espresso grid place-items-center text-xl shadow">&#9749;</span>
            <span class="leading-none"><span class="font-display text-xl font-extrabold tracking-tight">{{ config('pos.cafe_name') }}</span><span class="block text-[11px] text-crema/60 tracking-wider uppercase mt-0.5">Point of sale</span></span>
        </a>
        @isset($shift)
            @if ($shift)
                <a href="{{ route('pos.shift.show') }}" class="hidden sm:inline-flex items-center gap-1.5 text-xs bg-emerald-400/15 text-emerald-200 ring-1 ring-emerald-300/30 rounded-full px-3 py-1 hover:bg-emerald-400/25"><span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>Shift since {{ $shift->opened_at->format('H:i') }}</a>
            @endif
        @endisset
        <nav class="ml-auto flex items-center gap-1 text-sm">
            <form method="GET" action="{{ route('pos.find') }}" class="flex"><input name="no" autocomplete="off" placeholder="Find bill: {{ now()->format('d-m-Y') }}-007" class="w-36 sm:w-56 rounded-xl bg-white/10 px-3 py-2 text-sm text-crema placeholder:text-crema/50 focus:bg-white focus:text-espresso focus:outline-none"></form><a href="{{ route('pos.history') }}" class="hidden sm:block rounded-xl px-3 py-2 hover:bg-white/10 font-semibold">&#128338; History</a>@if (\Illuminate\Support\Facades\Schema::hasTable('credit_sales'))<a href="{{ route('pos.debts') }}" class="hidden sm:block rounded-xl px-3 py-2 hover:bg-white/10 font-semibold">&#128221; Debts</a>@endif<a href="{{ route('pos.bills') }}" class="rounded-xl px-3 py-2 hover:bg-white/10 font-semibold">&#129534; Bills @if ($openBills)<span class="ml-0.5 bg-saffron text-espresso text-xs font-extrabold rounded-full px-1.5">{{ $openBills }}</span>@endif</a>
            @if ($me->role === 'manager')<a href="{{ route('manage.index') }}" class="hidden sm:block rounded-xl px-3 py-2 hover:bg-white/10">Manage</a>@endif
            <span class="hidden md:flex items-center gap-2 pl-3 ml-1 border-l border-white/15">
                <span class="w-8 h-8 rounded-full bg-roast grid place-items-center font-bold">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                <span class="leading-tight">{{ $me->name }}<span class="block text-[11px] text-crema/60 capitalize">{{ $me->role }}</span></span>
            </span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl px-3 py-2 text-red-200 hover:bg-red-500/20" title="Log out"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v8"/><path d="M6.3 7.3a8 8 0 1 0 11.4 0"/></svg><span class="sr-only">Log out</span></button></form>
        </nav>
    </div>
</header>

@if (session('ok') || session('change') !== null || $errors->any())
    <div class="px-4 pt-3 space-y-2">
        @if (session('change') !== null)
            <div class="pop rounded-2xl bg-emerald-600 text-white px-5 py-4 flex items-center justify-between shadow-lg">
                <span class="font-bold text-lg">&#10003; Paid in cash</span>
                <span class="text-right"><span class="block text-xs uppercase tracking-wider opacity-80">Give change</span><span class="font-display text-3xl font-extrabold">KSh {{ number_format(session('change'), 2) }}</span></span>
            </div>
        @elseif (session('ok'))
            <div class="pop rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-900 px-4 py-2.5 text-sm font-medium">&#10003; {{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="pop rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 px-4 py-2.5 text-sm font-medium">&#9888; {{ $errors->first() }}</div>
        @endif
    </div>
@endif

<main class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] xl:grid-cols-[minmax(0,1fr)_420px] gap-5 p-4">
    <div id="menu-col" class="min-w-0 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pr-2">
        @if (! $ticket)
            <div class="relative overflow-hidden rounded-3xl bg-espresso text-crema p-6 sm:p-8 mb-5 shadow-xl">
                <div class="absolute -right-4 -top-8 text-[140px] opacity-10 select-none">&#9749;</div>
                <p class="text-crema/60 text-sm">{{ now()->format('l, j F') }}</p>
                <h2 class="font-display text-3xl sm:text-4xl font-extrabold mt-1">Karibu, {{ $me->name }}!</h2>
                <p class="text-crema/70 mt-1 mb-6">Start an order, tap the dishes, then send it to the kitchen.</p>
                <form method="POST" action="{{ route('pos.tickets.store') }}">@csrf
                    <button class="bg-saffron hover:bg-amber-400 text-espresso font-extrabold text-lg rounded-2xl px-8 py-4 shadow-lg shadow-black/30">+ New order</button>
                </form>
            </div>
        @endif
        @php $hasCredit = \Illuminate\Support\Facades\Schema::hasTable('credit_sales'); $isGlovo = $hasCredit && $ticket && ($ticket->channel ?? 'house') === 'glovo'; @endphp
@if ($hasCredit) @include('pos.partials.channel') @endif
@include('pos.partials.menu')
    </div>

    <aside id="ticket-panel" class="lg:sticky lg:top-20 self-start scroll-mt-20">
        <div class="rounded-3xl overflow-hidden shadow-xl shadow-roast/10 ring-1 ring-roast/10 bg-white">
        @if (! $ticket)
            <div class="p-8 text-center text-roast/70"><div class="text-5xl mb-2">&#129534;</div><p class="font-bold text-espresso">No order open</p><p class="text-sm">Tap <b>+ New order</b> to begin.</p></div>
        @else
            @php
                $st = ['open' => ['Taking order', 'bg-sky-100 text-sky-800'], 'sent' => ['With the kitchen', 'bg-amber-100 text-amber-900'], 'paid' => ['Paid', 'bg-emerald-100 text-emerald-800'], 'void' => ['Cancelled', 'bg-gray-200 text-gray-700']][$ticket->status] ?? [$ticket->status, 'bg-gray-100 text-gray-700'];
                $count = (int) $ticket->items->sum('qty');
                $empty = $ticket->items->isEmpty();
            @endphp
            <div class="bg-espresso text-crema px-5 py-4 flex items-center justify-between">
                <div><div class="text-[11px] uppercase tracking-widest text-crema/60">Order</div><div class="font-display text-2xl font-extrabold">#{{ $no }}</div></div>
                <span class="text-xs font-bold rounded-full px-3 py-1 {{ $st[1] }}">{{ $st[0] }}</span>
            </div>
            <div class="px-5 pt-3 pb-1 bg-[#fffdf9]">
                @if ($empty)
                    <div class="text-center py-8 text-roast/60"><div class="text-4xl mb-1">&#128072;</div>Tap dishes on the menu to add them</div>
                @else
                    <ul class="divide-y divide-dashed divide-roast/20">
                    @foreach ($ticket->items as $line)
                        <li class="py-2.5 flex items-center gap-3">
                            <div class="flex-1 min-w-0"><div class="font-semibold leading-tight truncate">{{ $line->item->name }}</div><div class="text-xs text-roast/60">KSh {{ number_format((float) $line->unit_price) }} each</div></div>
                            @if ($ticket->status === 'open')
                                <div class="flex items-center rounded-full bg-crema ring-1 ring-roast/15">
                                    <form method="POST" action="{{ route('pos.tickets.items.update', [$ticket, $line]) }}" data-soft>@csrf @method('PATCH')<input type="hidden" name="qty" value="{{ $line->qty - 1 }}"><button class="w-12 h-12 rounded-full text-xl font-bold hover:bg-white active:bg-amber-100" title="{{ $line->qty > 1 ? 'One less' : 'Remove' }}">{!! $line->qty > 1 ? '&minus;' : '&#128465;' !!}</button></form>
                                    <span class="w-8 text-center text-lg font-extrabold">{{ $line->qty }}</span>
                                    <form method="POST" action="{{ route('pos.tickets.items.update', [$ticket, $line]) }}" data-soft>@csrf @method('PATCH')<input type="hidden" name="qty" value="{{ $line->qty + 1 }}"><button class="w-12 h-12 rounded-full text-xl font-bold hover:bg-white active:bg-amber-100" title="One more">+</button></form>
                                </div>
                            @else
                                <span class="text-sm font-extrabold text-roast/80">&times;{{ $line->qty }}</span>
                            @endif
                            <div class="w-16 text-right font-bold tabular-nums">{{ number_format($line->subtotal) }}</div>
                        </li>
                    @endforeach
                    </ul>
                @endif
            </div>
            <div class="px-5 py-4 border-t-2 border-dashed border-roast/20">
                <div class="flex items-end justify-between mb-4">
                    <span class="text-sm text-roast/70">{{ $count }} item{{ $count === 1 ? '' : 's' }}</span>
                    <span><span class="text-xs uppercase tracking-wider text-roast/60 mr-1">Total</span><span class="font-display text-3xl font-extrabold">KSh {{ number_format($ticket->total) }}</span></span>
                </div>
                @if ($ticket->status === 'open')
                    <form method="POST" action="{{ route('pos.tickets.send', $ticket) }}">
                        @csrf
                        <input type="hidden" name="print_bill" value="1">
                        <button {{ $empty ? 'disabled' : '' }} class="w-full rounded-2xl bg-saffron hover:bg-amber-400 text-espresso font-extrabold text-lg py-4 shadow-md disabled:opacity-40 disabled:cursor-not-allowed">&#128293; Send to kitchen + print bill</button>
                    </form>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <form method="POST" action="{{ route('pos.tickets.send', $ticket) }}">@csrf<button {{ $empty ? 'disabled' : '' }} class="w-full rounded-xl py-2.5 text-sm font-semibold bg-crema hover:bg-amber-50 ring-1 ring-roast/15 disabled:opacity-40">&#127859; Kitchen only</button></form>
                        <button type="button" onclick="printBill('{{ route('pos.tickets.bill', $ticket) }}')" {{ $empty ? 'disabled' : '' }} class="w-full rounded-xl py-2.5 text-sm font-semibold bg-crema hover:bg-amber-50 ring-1 ring-roast/15 disabled:opacity-40">&#129534; Print bill</button>
                    </div>
                    <form method="POST" action="{{ route('pos.tickets.void', $ticket) }}" onsubmit="return confirm('Cancel this whole order?');" class="mt-3 text-center">@csrf<button class="text-xs text-red-600 hover:underline">Cancel order</button></form>
                @elseif ($ticket->status === 'sent')
                    @php
                        $sentAt = $ticket->items->pluck('sent_at')->filter()->max();
                        $payTab = ($errors->has('cash_received') || old('cash_received') !== null) ? 'cash' : 'mpesa'; if (old('credit_form') || $errors->has('credit')) $payTab = 'credit';
                        $t = (int) ceil($ticket->total);
                        $quick = collect([$t, (int) (ceil($t / 50) * 50), (int) (ceil($t / 100) * 100), (int) (ceil($t / 500) * 500), 1000, 2000])->filter(fn ($a) => $a >= $t)->unique()->take(4)->values();
                    @endphp
                    <div class="flex items-center justify-between gap-2 text-sm bg-amber-50 text-amber-900 rounded-xl px-3 py-2 mb-3">
                        <span><span class="animate-pulse">&#9679;</span> With the kitchen @if ($sentAt) since {{ \Illuminate\Support\Carbon::parse($sentAt)->format('H:i') }} @endif</span>
                        @include('pos.partials.print-status')
                    </div>
                    @if ($isGlovo) @include('pos.partials.glovo-pay') @else
<div class="text-xs font-bold uppercase tracking-wider text-roast/60 mb-2">How is the customer paying?</div>
                    <div class="grid {{ $hasCredit ? 'grid-cols-3' : 'grid-cols-2' }} gap-2 mb-3">
                        <button type="button" data-paytab="mpesa" class="paytab rounded-2xl py-3 font-extrabold ring-2 bg-white ring-roast/15">&#128241; M-Pesa</button>
                        <button type="button" data-paytab="cash" class="paytab rounded-2xl py-3 font-extrabold ring-2 bg-white ring-roast/15">&#128181; Cash</button>
@if ($hasCredit)<button type="button" data-paytab="credit" class="paytab rounded-2xl py-3 font-extrabold ring-2 bg-white ring-roast/15">&#128221; Credit</button>@endif
                    </div>
                    <div data-paypane="mpesa" class="{{ $payTab === 'mpesa' ? '' : 'hidden' }}">
                        @include('pos.partials.mpesa')
                    </div>
                    <div data-paypane="cash" class="{{ $payTab === 'cash' ? '' : 'hidden' }}">
                        <form method="POST" action="{{ route('pos.tickets.close', $ticket) }}" id="cash-form" data-total="{{ $ticket->total }}" class="space-y-3">
                            @csrf
                            <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-roast/60">Cash received (KSh)</span>
                                <input id="cash-in" data-keypad="money" data-keypad-label="Cash received" type="number" name="cash_received" step="0.01" min="0" required inputmode="decimal" value="{{ old('cash_received', (string) $t) }}" class="mt-1 w-full rounded-2xl ring-1 ring-roast/20 px-4 py-3 text-3xl font-display font-extrabold text-right focus:outline-none focus:ring-2 focus:ring-saffron">
                            </label>
                            <div class="grid grid-cols-4 gap-1.5">
                                @foreach ($quick as $amt)<button type="button" data-cash="{{ $amt }}" class="rounded-xl bg-crema ring-1 ring-roast/15 py-2 text-sm font-bold hover:bg-amber-50">{{ number_format($amt) }}</button>@endforeach
                            </div>
                            <div class="flex justify-between items-center rounded-xl bg-emerald-50 px-4 py-2"><span class="text-sm text-emerald-900">Change to give</span><span id="cash-change" class="font-display text-2xl font-extrabold text-emerald-800">KSh 0</span></div>
                            <button class="w-full rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-lg py-4 shadow-md">&#128181; Close bill (cash)</button>
                        </form>
                    </div>
                    @if ($hasCredit)<div data-paypane="credit" class="{{ $payTab === 'credit' ? '' : 'hidden' }}">@include('pos.partials.credit')</div>@endif
@endif
<div class="mt-3 text-center"><button type="button" onclick="document.getElementById('void-modal').classList.remove('hidden')" class="text-xs text-red-600 hover:underline">Cancel order&hellip;</button></div>
                    <div id="void-modal" class="{{ session('void_open') || $errors->has('manager_pin') || $errors->has('reason') ? '' : 'hidden' }} fixed inset-0 z-40 bg-black/50 flex items-end sm:items-center justify-center p-3">
                        <form method="POST" action="{{ route('pos.tickets.void', $ticket) }}" class="w-full max-w-md rounded-3xl bg-white p-5 shadow-2xl space-y-4">
                            @csrf
                            <div class="flex items-center justify-between">
                                <h3 class="font-display text-xl font-extrabold">Cancel order #{{ $no }}?</h3>
                                <button type="button" onclick="document.getElementById('void-modal').classList.add('hidden')" class="w-11 h-11 rounded-full hover:bg-crema text-2xl" title="Close">&times;</button>
                            </div>
                            <p class="text-sm text-roast/70">The kitchen already has this order. A <b>CANCELLED</b> slip will print so they stop.</p>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-roast/60 mb-2">Why?</div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach (['Customer left', 'Wrong order', 'Duplicate order', 'Kitchen cannot make it', 'Customer changed mind', 'Other'] as $why)
                                        <label class="cursor-pointer"><input type="radio" name="reason" value="{{ $why }}" class="peer sr-only" required @checked(old('reason') === $why)><span class="block rounded-xl ring-1 ring-roast/20 px-3 py-3 text-sm font-semibold text-center peer-checked:bg-espresso peer-checked:text-crema peer-checked:ring-espresso">{{ $why }}</span></label>
                                    @endforeach
                                </div>
                                <input name="reason_note" maxlength="150" value="{{ old('reason_note') }}" placeholder="Details (optional)" class="mt-2 w-full rounded-xl ring-1 ring-roast/20 px-3 py-2.5 text-sm">
                            </div>
                            @if ($me->role !== 'manager')
                                <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-roast/60">Manager PIN</span>
                                    <input type="password" name="manager_pin" inputmode="numeric" autocomplete="off" maxlength="10" required data-keypad="pin" data-keypad-label="Manager PIN" class="mt-1 w-full rounded-xl ring-1 ring-roast/20 px-4 py-3 text-2xl tracking-widest text-center">
                                </label>
                            @endif
                            <button class="w-full rounded-2xl bg-red-600 hover:bg-red-700 text-white font-extrabold text-lg py-4">Cancel order</button>
                        </form>
                    </div>
                @elseif ($ticket->status === 'paid')
                    @php $pay = $ticket->payments()->latest('id')->first(); @endphp
                    <div class="pop rounded-2xl bg-emerald-600 text-white p-5 text-center mb-3 shadow-lg">
                        <div class="text-4xl leading-none">&#10003;</div>
                        <div class="font-display text-2xl font-extrabold mt-1">PAID</div>
                        <div class="text-sm opacity-90">Payment recorded</div>
                        @if ($pay && $pay->kk_reference)
                            @php [$vLabel, $vTone] = $pay->verification(); @endphp
                            <div class="mt-2 text-sm">M-Pesa code <span class="font-mono font-bold text-lg tracking-wider">{{ $pay->kk_reference }}</span></div>
                            <div class="mt-1 inline-block text-xs font-semibold rounded-full px-3 py-1 {{ $vTone === 'ok' ? 'bg-white text-emerald-700' : 'bg-amber-200 text-amber-900' }}">{!! $vTone === 'ok' ? '&#10003;' : '&#9888;' !!} {{ $vLabel }}</div>
                        @elseif ($pay)
                            <div class="mt-1 text-sm">{{ $pay->method === 'credit' ? 'On account: ' . optional(optional($ticket->creditSale)->customer)->name : 'Paid in cash' }}</div>
                        @endif
                    </div>
                    <button type="button" onclick="printBill('{{ route('pos.tickets.bill', [$ticket, 'mode' => 'receipt']) }}')" class="w-full rounded-xl py-2.5 text-sm font-semibold bg-crema hover:bg-amber-50 ring-1 ring-roast/15 mb-2">&#129534; Print receipt</button>
                    <form method="POST" action="{{ route('pos.tickets.store') }}">@csrf<button class="w-full rounded-2xl bg-saffron hover:bg-amber-400 text-espresso font-extrabold text-lg py-4 shadow-md">+ New order</button></form>
                @endif
            </div>
        @endif
        </div>
    </aside>
</main>

<iframe id="bill-frame" title="bill" aria-hidden="true" style="position:absolute;width:0;height:0;border:0;"></iframe>
<script>
    function printBill(url) {
        var f = document.getElementById('bill-frame');
        f.onload = function () {
            try { f.contentWindow.focus(); f.contentWindow.print(); }
            catch (e) { window.open(url, '_blank'); }
        };
        f.src = url + (url.indexOf('?') < 0 ? '?' : '&') + 't=' + Date.now();
    }
    (function () {
        var tabs = document.querySelectorAll('[data-paytab]');
        function setTab(name, focus) {
            tabs.forEach(function (b) {
                var on = b.dataset.paytab === name;
                ['bg-espresso', 'text-crema', 'ring-espresso'].forEach(function (k) { b.classList.toggle(k, on); });
                ['bg-white', 'ring-roast/15'].forEach(function (k) { b.classList.toggle(k, !on); });
            });
            document.querySelectorAll('[data-paypane]').forEach(function (p) { p.classList.toggle('hidden', p.dataset.paypane !== name); });
            var c = document.getElementById('cash-in');
            if (focus && name === 'cash' && c) { c.focus(); c.select(); }
        }
        tabs.forEach(function (b) { b.addEventListener('click', function () { setTab(b.dataset.paytab, true); }); });
        var shown = document.querySelector('[data-paypane]:not(.hidden)');
        if (tabs.length) setTab(shown ? shown.dataset.paypane : 'mpesa', false);

        var form = document.getElementById('cash-form'), cash = document.getElementById('cash-in'), out = document.getElementById('cash-change');
        if (form && cash && out) {
            var total = parseFloat(form.dataset.total) || 0;
            var upd = function () {
                var ch = (parseFloat(cash.value) || 0) - total;
                out.textContent = 'KSh ' + Math.abs(ch).toLocaleString(undefined, { maximumFractionDigits: 2 }) + (ch < 0 ? ' short' : '');
                out.classList.toggle('text-red-700', ch < 0); out.classList.toggle('text-emerald-800', ch >= 0);
            };
            cash.addEventListener('input', upd);
            document.querySelectorAll('[data-cash]').forEach(function (b) { b.addEventListener('click', function () { cash.value = b.dataset.cash; upd(); }); });
            upd();
        }
    })();
</script>
@if (session('print_bill'))
    <script>window.addEventListener('load', function () { printBill(@json(session('print_bill'))); });</script>
@endif
@include('partials.touch')
@include('partials.footer')
</body>
</html>
