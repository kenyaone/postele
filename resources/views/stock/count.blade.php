@extends('layouts.manage')
@section('title', 'Daily stock sheet')
@php $q = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="flex flex-wrap items-center gap-3 mb-3 -mt-2">
    <span class="text-gray-600">{{ $date->format('l, j F Y') }}</span>
    @if ($canPickDate)
        <form method="GET" action="{{ route('stock.count') }}" class="flex gap-2 items-center">
            <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm">
            <button class="text-sm border rounded px-2 py-1 hover:bg-gray-50">Go</button>
        </form>
    @endif
    <a href="{{ route('stock.items') }}" class="ml-auto text-sm text-emerald-700 hover:underline">Stock items &rarr;</a>
</div>

<div class="bg-emerald-50 border border-emerald-200 rounded p-3 text-sm text-emerald-900 mb-4">
    <b>Morning:</b> enter what was bought in <b>Bought today</b>. &nbsp;<b>At closing:</b> count what is left and enter it in <b>Closing count</b>.<br>
    <b>Used</b> = Opening + Bought today &minus; Closing count. Opening is the last closing count, filled in for you.
</div>

@if ($rows->isEmpty())
    <div class="bg-white rounded shadow p-6 text-center text-gray-600">
        No stock items yet. <a href="{{ route('stock.items') }}" class="text-emerald-700 underline">Add stock items</a> (for example Milk in litres, Sugar in kg, Eggs in pieces) to start the daily sheet.
    </div>
@else
<form method="POST" action="{{ route('stock.count.save') }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">Item</th>
                    <th class="px-3 py-2 text-right">Opening</th>
                    <th class="px-3 py-2 text-right">+ Bought today</th>
                    <th class="px-3 py-2 text-right">&minus; Closing count</th>
                    <th class="px-3 py-2 text-right">= Used</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $r)
                <tr class="border-t" data-row>
                    <td class="px-3 py-2">
                        <div class="font-medium">{{ $r->item->name }}</div>
                        <div class="text-xs text-gray-500">{{ $r->item->unit }}@if ($r->by) &middot; saved by {{ $r->by }}@endif</div>
                    </td>
                    <td class="px-3 py-2 text-right">
                        @if ($r->firstDay)
                            <input type="number" step="any" min="0" inputmode="decimal" name="opening[{{ $r->item->id }}]" value="{{ $q($r->opening) }}" placeholder="start" data-opening class="w-20 border rounded px-2 py-1 text-right">
                            <div class="text-xs text-gray-500">first day: type it</div>
                        @else
                            <span data-opening-value="{{ $r->opening }}">{{ $q($r->opening) }}</span>
                            @if ($r->openingOld)<div class="text-xs text-amber-700">from {{ $r->openingFrom->format('D j M') }}</div>@endif
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        <input type="number" step="any" min="0" inputmode="decimal" name="received[{{ $r->item->id }}]" value="{{ $r->received ? $q($r->received) : '' }}" placeholder="0" data-received class="w-20 border rounded px-2 py-1 text-right">
                    </td>
                    <td class="px-3 py-2 text-right">
                        <input type="number" step="any" min="0" inputmode="decimal" name="closing[{{ $r->item->id }}]" value="{{ $q($r->closing) }}" placeholder="count" data-closing class="w-20 border rounded px-2 py-1 text-right">
                    </td>
                    <td class="px-3 py-2 text-right font-semibold" data-used>&ndash;</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap items-center gap-3 mt-3">
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded px-5 py-2">Save stock sheet</button>
        <span class="text-sm text-gray-500">Closing counted: {{ $closedCount }} of {{ $rows->count() }} items. You can save Bought in the morning and Closing later.</span>
    </div>
</form>
@endif

@if ($toBuy->isNotEmpty())
    <div class="bg-white rounded shadow p-4 mt-6">
        <a href="{{ route('stock.shopping.print') }}" target="_blank" class="float-right bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded px-3 py-1">&#128424;&nbsp; Print shopping list</a>
        <h2 class="font-semibold mb-2">Shopping list <span class="text-sm font-normal text-gray-500">(at or below reorder level)</span></h2>
        <table class="w-full text-sm">
            <thead class="text-gray-500 text-left"><tr><th class="py-1">Item</th><th class="py-1 text-right">Left</th><th class="py-1 text-right">Buy</th></tr></thead>
            <tbody>
            @foreach ($toBuy as $b)
                <tr class="border-t">
                    <td class="py-1">{{ $b->item->name }}</td>
                    <td class="py-1 text-right text-red-700">{{ $q($b->qty) }} {{ $b->item->unit }}</td>
                    <td class="py-1 text-right font-semibold">{{ $b->suggest !== null ? $q($b->suggest) . ' ' . $b->item->unit : 'restock' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<script>
(function () {
    function num(v) { return v === '' || v === null || v === undefined || isNaN(parseFloat(v)) ? null : parseFloat(v); }
    function update(tr) {
        var oIn = tr.querySelector('[data-opening]'), oVal = tr.querySelector('[data-opening-value]');
        var o = oIn ? num(oIn.value) : (oVal ? num(oVal.dataset.openingValue) : null);
        var r = num(tr.querySelector('[data-received]').value) || 0;
        var c = num(tr.querySelector('[data-closing]').value);
        var cell = tr.querySelector('[data-used]');
        cell.className = 'px-3 py-2 text-right font-semibold';
        if (o === null || c === null) { cell.innerHTML = '&ndash;'; return; }
        var used = Math.round((o + r - c) * 100) / 100;
        if (used < 0) { cell.textContent = used + ' check'; cell.classList.add('text-red-700'); cell.title = 'Closing is more than opening + bought: was a purchase not entered?'; }
        else { cell.textContent = used; cell.title = ''; }
    }
    document.querySelectorAll('tr[data-row]').forEach(function (tr) {
        tr.querySelectorAll('input').forEach(function (i) { i.addEventListener('input', function () { update(tr); }); });
        update(tr);
    });
})();
</script>
@endsection
