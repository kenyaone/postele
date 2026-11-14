@extends('layouts.manage')
@section('title', 'Bills')
@php
    $methods = ['cash' => 'Cash', 'credit' => 'On account', 'kk_stk' => 'M-Pesa prompt', 'kk_till_auto' => 'M-Pesa till', 'kk_till_manual' => 'M-Pesa code typed'];
    $tones = ['ok' => 'bg-emerald-100 text-emerald-800', 'warn' => 'bg-amber-100 text-amber-900', 'plain' => 'bg-gray-100 text-gray-700'];
    $no = fn ($t) => '#' . $t->order_no;
@endphp

@section('content')
<div class="flex flex-wrap gap-2 items-center mb-4 -mt-2">
    <a href="{{ route('pos.index') }}" class="border rounded px-3 py-1 text-sm bg-white hover:bg-gray-50">&larr; Back to POS</a>
    <form method="GET" action="{{ route('pos.bills') }}" class="flex flex-wrap gap-2 sm:ml-auto">
        <input type="search" name="code" value="{{ $code }}" placeholder="Find M-Pesa code" class="border rounded px-2 py-1 text-sm uppercase w-44">
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm">
        <button class="border rounded px-3 py-1 text-sm bg-white hover:bg-gray-50">Show</button>
    </form>
</div>

<div class="mb-4 flex flex-wrap gap-2 text-xs">
    <span class="rounded-full px-2.5 py-1 bg-sky-100 text-sky-800 font-semibold">Taking order</span><span class="text-gray-500 self-center">still adding items</span>
    <span class="rounded-full px-2.5 py-1 bg-red-100 text-red-800 font-semibold">NOT PAID</span><span class="text-gray-500 self-center">customer has not paid</span>
    <span class="rounded-full px-2.5 py-1 bg-orange-100 text-orange-800 font-semibold">OWES</span><span class="text-gray-500 self-center">on a customer account, pays later</span>
    <span class="rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-800 font-semibold">PAID · Cash / M-Pesa</span><span class="text-gray-500 self-center">money received</span>
    <span class="rounded-full px-2.5 py-1 bg-gray-200 text-gray-700 font-semibold">Cancelled</span>
</div>

<div class="bg-white rounded shadow mb-6">
    <div class="px-4 py-3 border-b flex flex-wrap justify-between gap-2">
        <h2 class="font-semibold text-red-800">&#10060; Not paid yet <span class="text-gray-500 font-normal">({{ $open->count() }}) &middot; customer has not paid</span></h2>
        <span class="font-semibold text-red-700">KSh {{ number_format($open->sum(fn ($t) => $t->total), 2) }} not paid</span>
    </div>
    @if ($open->isEmpty())
        <p class="p-4 text-sm text-gray-500">No unpaid bills right now. &#10003;</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Served by</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Opened</th><th class="px-3 py-2 text-right">Items</th><th class="px-3 py-2 text-right">Total KSh</th><th class="px-3 py-2"></th></tr>
            </thead>
            <tbody>
            @foreach ($open as $t)
                @php $mins = $t->opened_at ? $t->opened_at->diffInMinutes(now()) : 0; @endphp
                <tr class="border-t {{ $mins > 60 ? 'bg-amber-50' : '' }}">
                    <td class="px-3 py-2 font-semibold">{{ $no($t) }}</td>
                    <td class="px-3 py-2">{{ $t->opener->name ?? '?' }}</td>
                    @php [$pl, $pc] = $t->payLabel(); @endphp<td class="px-3 py-2"><span class="text-xs font-bold rounded-full px-2.5 py-1 {{ $pc }}">{{ $pl }}</span></td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $t->opened_at?->format('D H:i') }} <span class="text-xs {{ $mins > 60 ? 'text-amber-800 font-semibold' : 'text-gray-500' }}">({{ $t->opened_at?->diffForHumans(null, true) }})</span></td>
                    <td class="px-3 py-2 text-right">{{ $t->items->whereNull('void_at')->sum('qty') }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($t->total, 2) }}</td>
                    <td class="px-3 py-2 text-right">
                        @if ($canOpenAll || $t->opened_by === $userId)
                            <a href="{{ route('pos.tickets.show', $t) }}" class="text-emerald-700 hover:underline whitespace-nowrap">Open &rarr;</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

@if ($credit->isNotEmpty())
<div class="bg-white rounded shadow mb-6 ring-1 ring-orange-200">
    <div class="px-4 py-3 border-b flex flex-wrap justify-between gap-2 bg-orange-50">
        <h2 class="font-semibold text-orange-900">&#128221; Owes &middot; put on account, not paid <span class="font-normal">({{ $credit->count() }})</span></h2>
        <span class="font-semibold text-orange-900">KSh {{ number_format($credit->sum(fn ($t) => $t->total), 2) }} owed &middot; <a href="{{ route('pos.debts') }}" class="underline">Debts</a>@if ($canOpenAll) &middot; <a href="{{ route('manage.credit-fix', ['date' => $date->toDateString()]) }}" class="underline font-bold">Fix: link to M-Pesa</a>@endif</span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left">
            <tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Served by</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">Put on credit</th><th class="px-3 py-2 text-right">KSh</th></tr>
        </thead>
        <tbody>
        @foreach ($credit as $t)
            <tr class="border-t">
                <td class="px-3 py-2 font-semibold">{{ $no($t) }}</td>
                <td class="px-3 py-2">{{ $t->opener->name ?? '?' }}</td>
                <td class="px-3 py-2">{{ $t->creditSale?->customer?->name ?? '?' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $t->closed_at?->format('H:i') }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ number_format($t->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

<div class="bg-white rounded shadow">
    <div class="px-4 py-3 border-b flex flex-wrap justify-between gap-2">
        <h2 class="font-semibold">
            @if ($code !== '') Payments with M-Pesa code &ldquo;{{ $code }}&rdquo; @else &#9989; Paid (cash or M-Pesa) &middot; {{ $date->isToday() ? 'Today' : $date->format('D j M Y') }} @endif
            <span class="text-gray-500 font-normal">({{ $paid->count() }})</span>
        </h2>
        <span class="font-semibold">KSh {{ number_format($paid->sum(fn ($t) => $t->payments->sum('amount')), 2) }}</span>
    </div>
    @if ($paid->isEmpty())
        <p class="p-4 text-sm text-gray-500">No paid bills {{ $code !== '' ? 'with that code' : 'on this day' }}.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Paid at</th><th class="px-3 py-2">Served by</th><th class="px-3 py-2 text-right">KSh</th><th class="px-3 py-2">Method</th><th class="px-3 py-2">M-Pesa code</th><th class="px-3 py-2">Kopo Kopo check</th></tr>
            </thead>
            <tbody>
            @foreach ($paid as $t)
                @foreach ($t->payments as $p)
                    @php [$label, $tone, $detail] = $p->verification(); @endphp
                    <tr class="border-t">
                        <td class="px-3 py-2 font-semibold">{{ $no($t) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $p->received_at?->format($code !== '' ? 'D j M H:i' : 'H:i') }}</td>
                        <td class="px-3 py-2">{{ $t->opener->name ?? '?' }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $p->amount, 2) }}</td>
                        <td class="px-3 py-2">{{ $methods[$p->method] ?? $p->method }}</td>
                        <td class="px-3 py-2 font-mono font-semibold tracking-wide">{{ $p->kk_reference ?: '' }}</td>
                        <td class="px-3 py-2">
                            <span class="text-xs rounded px-2 py-0.5 whitespace-nowrap {{ $tones[$tone] }}">{!! $tone === 'ok' ? '&#10003; ' : ($tone === 'warn' ? '&#9888; ' : '') !!}{{ $label }}</span>
                            @if ($detail)<div class="text-xs text-gray-500 mt-0.5">{{ $detail }}</div>@endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
