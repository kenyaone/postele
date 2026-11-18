@extends('layouts.manage')
@section('title', 'Clear my bills')

@section('content')
<div class="flex flex-wrap gap-2 items-center mb-4 -mt-2">
    <a href="{{ route('pos.index') }}" class="border rounded px-3 py-1 text-sm bg-white hover:bg-gray-50">&larr; Back to POS</a>
    @if ($isManager)
        <form method="GET" action="{{ route('pos.clear') }}" class="flex gap-2 sm:ml-auto">
            <select name="waiter" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                <option value="0">All waiters</option>
                @foreach ($waiters as $w)
                    <option value="{{ $w->id }}" @selected($waiterId === $w->id)>{{ $w->name }}</option>
                @endforeach
            </select>
        </form>
    @endif
</div>

@if (session('ok'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-900 text-sm p-3 font-semibold">&#10003; {{ session('ok') }}</div>
@endif
@error('clear')
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 text-sm p-3 font-semibold">&#9888; {{ $message }}</div>
@enderror
@foreach (['event_id', 'bills'] as $f)
    @error($f)<div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 text-sm p-3 font-semibold">&#9888; {{ $message }}</div>@enderror
@endforeach

<p class="mb-4 text-sm text-gray-600">Money that came into the till without a bill (Kopo Kopo app, or the customer paid the till) shows on the left.
    <b>1.</b> Tap the payment. <b>2.</b> Tick {{ $isManager ? 'the' : 'your' }} bills it paid for. <b>3.</b> Press <b>Link</b>. Do this before you go home &mdash; you are paid only when all your bills are matched.</p>

<form method="POST" action="{{ route('pos.clear.store', $isManager && $waiterId ? ['waiter' => $waiterId] : []) }}" id="clearForm">
@csrf
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded shadow">
        <div class="px-4 py-3 border-b flex justify-between gap-2">
            <h2 class="font-semibold">&#128241; 1. M-Pesa not linked to a bill <span class="text-gray-500 font-normal">({{ $events->count() }})</span></h2>
            <a href="{{ url()->full() }}" class="text-xs font-semibold text-green-700 hover:underline self-center">&#8635; Refresh</a>
        </div>
        @if ($events->isEmpty())
            <p class="p-4 text-sm text-gray-500">No unlinked till payments in the last 3 days. &#10003;</p>
        @else
            <div class="p-2 space-y-1.5 max-h-[70vh] overflow-y-auto">
            @foreach ($events as $ev)
                @php
                    $r = $ev->raw_payload['event']['resource'] ?? [];
                    $who = trim(($r['sender_first_name'] ?? '') . ' ' . ($r['sender_last_name'] ?? ''));
                @endphp
                <label class="ev flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-gray-200 cursor-pointer hover:bg-green-50" data-amount="{{ (float) $ev->amount }}" data-at="{{ $ev->received_at?->timestamp }}">
                    <input type="radio" name="event_id" value="{{ $ev->id }}" class="w-5 h-5 accent-green-600" @checked((int) old('event_id') === $ev->id)>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold truncate">{{ $who !== '' ? $who : 'Customer' }}</span>
                        <span class="block text-xs text-gray-500 font-mono">{{ $ev->reference }} &middot; {{ $ev->received_at?->format('D H:i') }}</span>
                    </span>
                    <span class="font-extrabold shrink-0">KSh {{ number_format((float) $ev->amount) }}</span>
                </label>
            @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded shadow">
        <div class="px-4 py-3 border-b flex justify-between gap-2">
            <h2 class="font-semibold text-red-800">&#10060; 2. {{ $isManager ? 'Unpaid bills' : 'Your unpaid bills' }} <span class="text-gray-500 font-normal">({{ $bills->count() }})</span></h2>
            <span class="font-semibold text-red-700 self-center text-sm">KSh {{ number_format($bills->sum(fn ($t) => $t->balance)) }}</span>
        </div>
        @if ($bills->isEmpty())
            <p class="p-4 text-sm text-gray-500">No unpaid bills. &#10003;</p>
        @else
            <div class="p-2 space-y-1 max-h-[70vh] overflow-y-auto">
            @php $day = null; @endphp
            @foreach ($bills as $t)
                @if ($day !== $t->opened_at?->toDateString())
                    @php $day = $t->opened_at?->toDateString(); @endphp
                    <div class="px-2 pt-2 text-xs font-bold uppercase tracking-wider text-gray-500">{{ $t->opened_at?->isToday() ? 'Today' : $t->opened_at?->format('D j M') }}</div>
                @endif
                <label class="bill flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-green-50 cursor-pointer" data-at="{{ $t->opened_at?->timestamp }}">
                    <input type="checkbox" name="bills[]" value="{{ $t->id }}" class="w-5 h-5 accent-green-600" data-total="{{ (float) $t->balance }}" @checked(in_array($t->id, (array) old('bills', [])))>
                    <span class="flex-1 min-w-0 truncate"><b>#{{ $t->order_no }}</b>
                        <span class="text-xs text-gray-500">{{ $t->opened_at?->format('H:i') }}@if ($isManager) &middot; {{ $t->opener->name ?? '?' }}@endif &middot; {{ $t->items->whereNull('void_at')->map(fn ($i) => $i->qty . ' ' . ($i->item->name ?? ''))->take(3)->implode(', ') }}</span>
                        <span class="late hidden text-[11px] font-bold text-amber-700">opened after this payment</span>
                    </span>
                    <span class="font-semibold shrink-0">KSh {{ number_format($t->balance) }}</span>
                </label>
            @endforeach
            </div>
        @endif
    </div>
</div>

<div class="sticky bottom-0 mt-4 bg-white rounded-xl shadow ring-1 ring-gray-200 p-3 flex flex-wrap items-center gap-3">
    <div class="text-sm">Payment <b id="cPay">&mdash;</b> &middot; Ticked bills <b id="cBills">KSh 0</b> <span id="cNote" class="ml-1 font-bold"></span></div>
    <button id="cGo" disabled class="ml-auto rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 disabled:opacity-40 disabled:cursor-not-allowed">Link</button>
</div>
</form>

<script>
(function () {
    var form = document.getElementById('clearForm'), MGR = @json($isManager);
    function ev() { return form.querySelector('input[name="event_id"]:checked'); }
    function ticked() { return Array.prototype.slice.call(form.querySelectorAll('input[name="bills[]"]:checked')); }
    function fmt(n) { return 'KSh ' + Math.round(n).toLocaleString(); }
    function refresh() {
        var e = ev(), pay = e ? parseFloat(e.closest('.ev').dataset.amount) : null, at = e ? parseInt(e.closest('.ev').dataset.at || 0, 10) : 0;
        var b = ticked(), sum = b.reduce(function (s, x) { return s + parseFloat(x.dataset.total || 0); }, 0);
        form.querySelectorAll('.ev').forEach(function (l) { var on = l.querySelector('input').checked; l.classList.toggle('ring-2', on); l.classList.toggle('ring-green-500', on); l.classList.toggle('bg-green-50', on); });
        form.querySelectorAll('.bill').forEach(function (l) { var late = at && parseInt(l.dataset.at || 0, 10) > at; l.querySelector('.late').classList.toggle('hidden', ! late); l.classList.toggle('opacity-50', late); });
        document.getElementById('cPay').textContent = pay === null ? 'not chosen' : fmt(pay);
        document.getElementById('cBills').textContent = fmt(sum) + (b.length > 1 ? ' (' + b.length + ' bills)' : '');
        var note = document.getElementById('cNote'), ok = false;
        if (pay === null || ! b.length) { note.textContent = ''; }
        else if (Math.abs(pay - sum) < 0.01) { note.textContent = ' EXACT'; note.className = 'ml-1 font-bold text-green-700'; ok = true; }
        else if (pay > sum && (pay - sum <= 50.004 || MGR)) { note.textContent = fmt(pay - sum) + ' extra goes on the last bill'; note.className = 'ml-1 font-bold text-amber-700'; ok = true; }
        else if (pay > sum) { note.textContent = fmt(pay - sum) + ' more than the bills - tick the other bills it paid for'; note.className = 'ml-1 font-bold text-red-700'; }
        else if (b.length === 1) { note.textContent = 'part payment, ' + fmt(sum - pay) + ' stays unpaid'; note.className = 'ml-1 font-bold text-amber-700'; ok = true; }
        else { note.textContent = 'short by ' + fmt(sum - pay) + ' - untick a bill'; note.className = 'ml-1 font-bold text-red-700'; }
        document.getElementById('cGo').disabled = ! ok;
    }
    form.addEventListener('change', refresh);
    form.addEventListener('submit', function (e) {
        var x = ev(), b = ticked();
        if (! x || ! b.length) { e.preventDefault(); return; }
        if (! confirm('Link ' + document.getElementById('cPay').textContent + ' to ' + b.length + ' bill(s)? Only do this if the customer really paid these bills with this payment.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endsection
