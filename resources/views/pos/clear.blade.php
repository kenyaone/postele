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

@if ($isManager)
<p class="mb-4 text-sm text-gray-600">Money that came into the till without a bill shows on the left.
    <b>1.</b> Tap the payment, or type its M-Pesa code. <b>2.</b> Tick the bills it paid for. <b>3.</b> Press <b>Link</b> (or <b>Verify code</b>). For another waiter's bill, write how you know.</p>
@else
<div class="mb-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
    <div class="font-bold text-base mb-1">Clear your unpaid bills with the M-Pesa code</div>
    <b>1.</b> Tick the bill (or bills) the customer paid. <b>2.</b> Type the M-Pesa code from the customer's SMS (e.g. UKILD26AD2). <b>3.</b> Press <b>Verify code</b>.
    The bill is cleared only if Kopo Kopo received that code. If the customer paid cash, tell the manager. You are paid only when all your bills are cleared.
</div>
@endif

<form method="POST" action="{{ route('pos.clear.store', $isManager && $waiterId ? ['waiter' => $waiterId] : []) }}" id="clearForm">
@csrf
<div class="grid grid-cols-1 {{ $isManager ? 'lg:grid-cols-2' : '' }} gap-4">
    @if ($isManager)
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
                    $digits = preg_replace('/\D/', '', (string) ($r['sender_phone_number'] ?? ''));
                    $phone = strlen($digits) >= 9 ? '0' . substr($digits, -9, 3) . ' *** ' . substr($digits, -3) : '';
                @endphp
                <label class="ev flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-gray-200 cursor-pointer hover:bg-green-50" data-amount="{{ (float) $ev->amount }}" data-at="{{ $ev->received_at?->timestamp }}">
                    <input type="radio" name="event_id" value="{{ $ev->id }}" class="w-5 h-5 accent-green-600" @checked((int) old('event_id') === $ev->id)>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold truncate">{{ $who !== '' ? $who : ($phone !== '' ? $phone : 'Customer') }}</span>
                        <span class="block text-xs text-gray-500 font-mono">{{ $ev->received_at?->format('D H:i') }} &middot; {{ $ev->reference }}</span>
                        <span class="hint block text-xs font-bold text-green-700"></span>
                    </span>
                    <span class="font-extrabold shrink-0">KSh {{ number_format((float) $ev->amount) }}</span>
                </label>
            @endforeach
            </div>
        @endif
    </div>
    @endif

    <div class="bg-white rounded shadow">
        <div class="px-4 py-3 border-b flex justify-between gap-2">
            <h2 class="font-semibold text-red-800">&#10060; {{ $isManager ? '2.' : '1.' }} {{ $isManager ? 'Unpaid bills' : 'Your unpaid bills' }} <span class="text-gray-500 font-normal">({{ $bills->count() }})</span></h2>
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
                        <span class="same hidden text-[11px] font-bold text-green-700">&#10003; same amount</span>
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
    <input name="code" id="cCode" value="{{ old('code') }}" maxlength="14" autocomplete="off" autocapitalize="characters" placeholder="M-Pesa code e.g. UKILD26AD2" class="border-2 border-green-600 rounded-lg px-3 py-2 font-mono uppercase text-base w-56">
    <button id="cVerify" formaction="{{ route('pos.clear.code', $isManager && $waiterId ? ['waiter' => $waiterId] : []) }}" disabled class="rounded-xl bg-green-700 hover:bg-green-800 text-white font-bold px-6 py-3 disabled:opacity-40 disabled:cursor-not-allowed">Verify code</button>
    @if ($isManager)
        <input name="reason" value="{{ old('reason') }}" maxlength="150" placeholder="How do you know? (needed for other waiters' bills)" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[14rem]">
    @endif
    @if ($isManager)<button id="cGo" disabled class="ml-auto rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 disabled:opacity-40 disabled:cursor-not-allowed">Link</button>@endif
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
        var go = document.getElementById('cGo'); if (go) go.disabled = ! ok;
        var code = (document.getElementById('cCode').value || '').replace(/\s/g, '');
        document.getElementById('cVerify').disabled = ! (b.length && /^[A-Za-z0-9]{8,12}$/.test(code));
    }
    // Bills opened before the payment with exactly the payment amount, newest first (closest in time).
    function matches(l) {
        var pay = parseFloat(l.dataset.amount), at = parseInt(l.dataset.at || 0, 10);
        return Array.prototype.slice.call(form.querySelectorAll('.bill')).filter(function (b) {
            var bt = parseInt(b.dataset.at || 0, 10), tot = parseFloat(b.querySelector('input').dataset.total || 0);
            return (! at || bt <= at) && Math.abs(tot - pay) < 0.01;
        }).sort(function (a, b) { return parseInt(b.dataset.at || 0, 10) - parseInt(a.dataset.at || 0, 10); });
    }
    function hints() {
        form.querySelectorAll('.ev').forEach(function (l) {
            var m = matches(l), h = l.querySelector('.hint');
            h.textContent = m.length === 1 ? 'Same amount as #' + m[0].querySelector('b').textContent.replace('#', '')
                : (m.length > 1 ? m.length + ' bills with this amount' : '');
        });
    }
    form.addEventListener('change', function (e) {
        // Tapping a payment pre-ticks the bill it fits, when only one bill fits. The waiter still checks and presses Link.
        if (e.target.name === 'event_id') {
            ticked().forEach(function (x) { x.checked = false; });
            var lab = e.target.closest('.ev'), m = matches(lab);
            form.querySelectorAll('.bill').forEach(function (b) { b.querySelector('.same').classList.toggle('hidden', m.indexOf(b) < 0); });
            if (m.length === 1) m[0].querySelector('input').checked = true;
            if (m.length) m[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        refresh();
    });
    hints();
    document.getElementById('cCode').addEventListener('input', refresh);
    form.addEventListener('submit', function (e) {
        if (e.submitter && e.submitter.id === 'cVerify') return;   // code is checked against Kopo Kopo on the server
        var x = ev(), b = ticked();
        if (! x || ! b.length) { e.preventDefault(); return; }
        if (! confirm('Link ' + document.getElementById('cPay').textContent + ' to ' + b.length + ' bill(s)? Only do this if the customer really paid these bills with this payment.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endsection
