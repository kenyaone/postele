@extends('layouts.manage')
@section('title', 'Cancelled orders')
@php $no = fn ($t) => '#' . str_pad($t->id, 5, '0', STR_PAD_LEFT); @endphp

@section('content')
<form method="GET" action="{{ route('manage.voids') }}" class="flex flex-wrap items-center gap-2 mb-4 -mt-2">
    <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded-lg px-3 py-2 text-sm">
    <select name="days" class="border rounded-lg px-3 py-2 text-sm">
        <option value="1" @selected($days === 1)>That day</option>
        <option value="7" @selected($days === 7)>7 days to then</option>
        <option value="30" @selected($days === 30)>30 days to then</option>
    </select>
    <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">Show</button>
</form>

<div class="grid grid-cols-2 gap-3 mb-5 max-w-2xl">
    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5">
        <div class="text-xs uppercase tracking-wider text-gray-500">Cancelled {{ $days === 1 ? ($date->isToday() ? 'today' : $date->format('D j M')) : $from->format('j M') . ' - ' . $date->format('j M') }}</div>
        <div class="text-2xl font-extrabold mt-1">{{ $stats->count }} <span class="text-base font-semibold text-gray-500">orders</span></div>
        <div class="text-sm text-gray-600">KSh {{ number_format($stats->value) }}</div>
    </div>
    <div class="rounded-2xl p-4 shadow ring-1 {{ $stats->afterCount ? 'bg-red-50 ring-red-200' : 'bg-white ring-black/5' }}">
        <div class="text-xs uppercase tracking-wider {{ $stats->afterCount ? 'text-red-800' : 'text-gray-500' }}">After the kitchen had them</div>
        <div class="text-2xl font-extrabold mt-1 {{ $stats->afterCount ? 'text-red-700' : 'text-gray-400' }}">{{ $stats->afterCount }} <span class="text-base font-semibold">orders</span></div>
        <div class="text-sm {{ $stats->afterCount ? 'text-red-800' : 'text-gray-500' }}">KSh {{ number_format($stats->afterValue) }} of food</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
    @if ($rows->isEmpty())
        <p class="p-6 text-center text-gray-500">No cancelled orders in this period. &#10003;</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider">
                <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Dishes</th><th class="px-4 py-3 text-right">KSh</th><th class="px-4 py-3">Why</th><th class="px-4 py-3">Cancelled by</th><th class="px-4 py-3">Approved by</th></tr>
            </thead>
            <tbody class="divide-y">
            @foreach ($rows as $t)
                <tr class="{{ $t->wasSent ? 'bg-red-50/50' : '' }}">
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $t->closed_at?->format($days === 1 ? 'H:i' : 'D j M H:i') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="font-bold">{{ $no($t) }}</div>
                        <div class="text-xs text-gray-500">{{ $t->opener->name ?? '?' }}</div>
                        @if ($t->wasSent)<span class="inline-block mt-1 text-[11px] font-bold rounded-full bg-red-100 text-red-800 px-2 py-0.5">After kitchen</span>@else<span class="inline-block mt-1 text-[11px] rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">Before kitchen</span>@endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $t->items->map(fn ($l) => $l->qty . ' x ' . ($l->item->name ?? '?'))->implode(', ') ?: 'Empty order' }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($t->total) }}</td>
                    <td class="px-4 py-3">{{ $t->void_reason ?: 'No reason recorded' }}</td>
                    <td class="px-4 py-3">{{ $t->voider->name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $t->approver->name ?? ($t->wasSent ? 'Not recorded' : '-') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
<p class="text-xs text-gray-500 mt-3">Orders cancelled after the kitchen had them need a manager PIN and a reason, and print a CANCELLED slip.</p>
@endsection
