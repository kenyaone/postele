@extends('layouts.manage')
@section('title', 'Fix credit bills')

@php $ksh = fn ($n) => 'KSh ' . number_format((float) $n); @endphp

@section('content')
<div class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" class="flex items-center gap-2">
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm">
        <button class="border rounded px-3 py-1 text-sm hover:bg-gray-50">Show</button>
    </form>
    <div class="text-sm ml-auto flex flex-wrap gap-2">
        <span class="rounded-full bg-orange-100 text-orange-900 px-3 py-1 font-semibold">{{ $bills->count() }} bills on credit &middot; {{ $ksh($owed) }}</span>
        <span class="rounded-full bg-green-100 text-green-900 px-3 py-1 font-semibold">{{ $cards->count() }} M-Pesa not linked &middot; {{ $ksh($loose) }}</span>
    </div>
</div>

@if (session('ok'))<div class="mb-4 rounded-lg bg-emerald-50 text-emerald-900 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>@endif
@error('fix')<div class="mb-4 rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm font-semibold">{{ $message }}</div>@enderror

<p class="text-sm text-gray-600 mb-4">Each card is a till payment Kopo Kopo received that is not linked to a bill. Tick the bill(s) it paid and press <b>Link</b>. The bills become paid by M-Pesa with that code. Bills the customer paid in cash: use <b>Paid cash</b> in the list at the bottom. Nothing prints.</p>

<div class="grid md:grid-cols-2 gap-4 mb-8">
    @forelse ($cards as $c)
        <form method="POST" action="{{ route('manage.credit-fix.link') }}" class="bg-white rounded-xl shadow p-4 fix-card" data-amount="{{ (float) $c->e->amount }}">
            @csrf
            <input type="hidden" name="event_id" value="{{ $c->e->id }}">
            <div class="flex items-baseline justify-between gap-2">
                <div><span class="text-xl font-extrabold">{{ $ksh($c->e->amount) }}</span> <span class="text-sm text-gray-500">{{ $c->at->format('H:i') }}</span></div>
                <div class="font-mono text-sm font-semibold">{{ $c->e->reference }}</div>
            </div>
            <div class="text-sm text-gray-600 mb-2">{{ $c->name ?: 'Payer name not sent' }}</div>
            <div class="space-y-1 max-h-64 overflow-y-auto">
                @forelse ($c->near as $b)
                    <label class="flex items-center gap-2 text-sm rounded px-2 py-1 hover:bg-amber-50 {{ abs($b->amount - $c->e->amount) < .01 ? 'bg-green-50 ring-1 ring-green-300' : '' }}">
                        <input type="checkbox" name="bills[]" value="{{ $b->ticket_id }}" data-amt="{{ (float) $b->amount }}" class="w-4 h-4 accent-green-600">
                        <span class="font-semibold">{{ $b->ticket->order_no }}</span>
                        <span class="text-gray-500 truncate flex-1">{{ $b->created_at->format('H:i') }} &middot; {{ $b->ticket->opener->name ?? '?' }} &middot; {{ $b->customer->name ?? '' }} &middot; {{ $b->ticket->items->whereNull('void_at')->map(fn ($i) => $i->qty . ' ' . ($i->item->name ?? ''))->take(2)->implode(', ') }}</span>
                        <span class="font-semibold shrink-0">{{ number_format($b->amount) }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-400">No credit bills around this time.</p>
                @endforelse
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-sm">Ticked: <b class="fix-sum">0</b></span>
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg px-4 py-2">Link</button>
            </div>
        </form>
    @empty
        <div class="bg-white rounded-xl shadow p-6 text-gray-500">All M-Pesa payments for this day are linked. &#10003;</div>
    @endforelse
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto">
    <div class="px-4 py-3 border-b font-semibold">Bills still on credit</div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Time</th><th class="px-3 py-2">Waiter</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">What</th><th class="px-3 py-2 text-right">KSh</th><th class="px-3 py-2"></th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
            <tr class="border-t">
                <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $b->ticket->order_no }}</td>
                <td class="px-3 py-2">{{ $b->created_at->format('H:i') }}</td>
                <td class="px-3 py-2">{{ $b->ticket->opener->name ?? '?' }}</td>
                <td class="px-3 py-2">{{ $b->customer->name ?? '?' }}</td>
                <td class="px-3 py-2 text-gray-500">{{ $b->ticket->items->whereNull('void_at')->map(fn ($i) => $i->qty . ' x ' . ($i->item->name ?? ''))->implode(', ') }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ number_format($b->amount) }}</td>
                <td class="px-3 py-2 text-right">
                    <form method="POST" action="{{ route('manage.credit-fix.cash', $b->ticket_id) }}" onsubmit="return confirm('Mark {{ $b->ticket->order_no }} as paid in cash? Collect the cash from the waiter.');">
                        @csrf<button class="text-xs rounded border px-2 py-1 hover:bg-gray-50 whitespace-nowrap">Paid cash</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No bills on credit for this day.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.fix-card').forEach(function (card) {
    var amt = parseFloat(card.dataset.amount), out = card.querySelector('.fix-sum');
    function upd() {
        var s = 0; card.querySelectorAll('input[name="bills[]"]:checked').forEach(function (b) { s += parseFloat(b.dataset.amt); });
        out.textContent = s.toLocaleString() + (Math.abs(s - amt) < .01 ? ' ✓ exact' : (s > amt ? ' (more than paid)' : ''));
        out.className = 'fix-sum ' + (Math.abs(s - amt) < .01 ? 'text-green-700' : (s > amt ? 'text-red-700' : ''));
    }
    card.querySelectorAll('input[name="bills[]"]').forEach(function (b) { b.addEventListener('change', upd); });
});
</script>
@endsection
