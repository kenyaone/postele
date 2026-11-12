@extends('layouts.manage')
@section('title', 'Closing stock count')
@php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="flex flex-wrap items-center gap-3 mb-4 -mt-2">
    <span class="text-gray-600">{{ $date->format('l, j F Y') }}</span>
    @if ($canPickDate)
        <form method="GET" action="{{ route('stock.count') }}" class="flex gap-2 items-center">
            <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm">
            <button class="text-sm border rounded px-2 py-1 hover:bg-gray-50">Go</button>
        </form>
    @endif
    <a href="{{ route('stock.items') }}" class="text-sm text-emerald-700 hover:underline ml-auto">Manage stock items</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('stock.count.save') }}" class="lg:col-span-2 bg-white rounded-xl shadow p-4">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        @if ($items->isEmpty())
            <p class="text-gray-500">No stock items yet. <a href="{{ route('stock.items') }}" class="text-emerald-700 underline">Add your stock items</a> first (e.g. potatoes in kg, eggs in trays).</p>
        @else
            <p class="text-sm text-gray-500 mb-3">Count what is left at closing and type the amount. Leave blank anything you did not count.</p>
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Item</th><th class="py-1">Last count</th><th class="py-1 w-36">Count now</th></tr></thead>
                <tbody>
                @foreach ($items as $i)
                    @php $last = $latest[$i->id] ?? null; $now = $onDate[$i->id] ?? null; $low = $last && (float) $last->quantity <= (float) $i->reorder_level; @endphp
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-medium">{{ $i->name }} <span class="text-xs text-gray-400">reorder at {{ $q($i->reorder_level) }} {{ $i->unit }}</span></td>
                        <td class="py-2 {{ $low ? 'text-red-700 font-semibold' : 'text-gray-500' }}">
                            @if ($last) {{ $q($last->quantity) }} {{ $i->unit }} <span class="text-xs">({{ $last->counted_on->isSameDay($date) ? 'today' : $last->counted_on->format('j M') }})</span> @else <span class="text-gray-400">never</span> @endif
                        </td>
                        <td class="py-2">
                            <div class="flex items-center gap-1">
                                <input type="number" name="counts[{{ $i->id }}]" step="0.01" min="0" inputmode="decimal" value="{{ old('counts.' . $i->id, $now ? $q($now->quantity) : '') }}" class="w-24 border rounded px-2 py-1 text-right">
                                <span class="text-xs text-gray-500">{{ $i->unit }}</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <button class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">Save counts</button>
        @endif
    </form>

    <div class="bg-white rounded-xl shadow p-4 h-fit" id="shopping-list">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-semibold">Shopping list</h2>
            <button type="button" onclick="window.print()" class="text-sm text-emerald-700 hover:underline print:hidden">Print</button>
        </div>
        @if ($toBuy->isEmpty())
            <p class="text-sm text-gray-500">Nothing is at or below its reorder level.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Item</th><th class="py-1 text-right">Left</th><th class="py-1 text-right">Buy</th></tr></thead>
                <tbody>
                @foreach ($toBuy as $b)
                    <tr class="border-b last:border-0">
                        <td class="py-1">{{ $b->item->name }}</td>
                        <td class="py-1 text-right text-red-700">{{ $q($b->qty) }} {{ $b->item->unit }}</td>
                        <td class="py-1 text-right font-semibold">{{ $b->suggest !== null ? $q($b->suggest) . ' ' . $b->item->unit : 'restock' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if ($notCounted)<p class="text-xs text-amber-700 mt-3">{{ $notCounted }} item{{ $notCounted === 1 ? '' : 's' }} never counted, so not checked.</p>@endif
    </div>
</div>
@endsection
