@extends('layouts.manage')
@section('title', 'Dashboard')

@php
    $ksh = fn ($v) => 'KSh ' . number_format((float) $v, 0);
    $costToday = $expToday + $purToday + $payToday; $netToday = $salesToday - $costToday;
    $costMonth = $expMonth + $purMonth + $payMonth; $netMonth = $salesMonth - $costMonth;
    $methodLabels = ['cash' => 'Cash', 'kk_stk' => 'M-Pesa STK', 'kk_till_auto' => 'M-Pesa Till', 'kk_till_manual' => 'M-Pesa Till (manual)'];
@endphp

@section('content')
@include('manage.partials.yield-alerts')
    <p class="text-sm text-gray-500 -mt-4 mb-6">{{ now()->format('l, j F Y \a\t H:i') }}</p>

    {{-- TODAY --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs uppercase text-gray-500">Money received today</div>
            <div class="text-2xl font-bold text-emerald-700">{{ $ksh($salesToday) }}</div>
            <div class="text-xs text-gray-500 mt-1">
                @forelse ($byMethod as $m => $amt) {{ $methodLabels[$m] ?? $m }} {{ $ksh($amt) }}@if (! $loop->last) &middot; @endif
                @empty No payments yet @endforelse
            </div>
            @if ($creditToday > 0)<div class="text-xs text-orange-700 font-semibold mt-1">+ {{ $ksh($creditToday) }} on credit ({{ $creditBills }} bill{{ $creditBills === 1 ? '' : 's' }}), not paid yet</div>@endif
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs uppercase text-gray-500">Bills paid</div>
            <div class="text-2xl font-bold">{{ $ticketsToday }}</div>
            <div class="text-xs text-gray-500 mt-1">Avg {{ $ksh($avgTicket) }} @if ($voidsToday) &middot; <span class="text-red-600">{{ $voidsToday }} void</span> @endif</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs uppercase text-gray-500">Costs today</div>
            <div class="text-2xl font-bold text-red-700">{{ $ksh($costToday) }}</div>
            <div class="text-xs text-gray-500">Exp {{ $ksh($expToday) }} &middot; Purch {{ $ksh($purToday) }} &middot; Pay {{ $ksh($payToday) }}</div>
            <a href="{{ route('manage.expenses.index') }}" class="text-xs text-emerald-700 hover:underline">+ Record expense</a>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs uppercase text-gray-500">Net today</div>
            <div class="text-2xl font-bold {{ $netToday < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $ksh($netToday) }}</div>
            <div class="text-xs text-gray-500 mt-1">Sales minus all costs</div>
        </div>
    </div>

    {{-- MONTH --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6 grid grid-cols-3 gap-4 text-center">
        <div><div class="text-xs uppercase text-gray-500">{{ now()->format('F') }} sales</div><div class="text-lg font-bold text-emerald-700">{{ $ksh($salesMonth) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">{{ now()->format('F') }} costs</div><div class="text-lg font-bold text-red-700">{{ $ksh($costMonth) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">{{ now()->format('F') }} net</div><div class="text-lg font-bold {{ $netMonth < 0 ? 'text-red-700' : '' }}">{{ $ksh($netMonth) }}</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 7-DAY CHART --}}
        <div class="bg-white rounded-xl shadow p-4 lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-semibold">Last 7 days</h2>
                <div class="text-xs text-gray-500">
                    <span class="inline-block w-3 h-3 bg-emerald-500 rounded-sm align-middle"></span> Sales
                    <span class="inline-block w-3 h-3 bg-red-400 rounded-sm align-middle ml-3"></span> Costs
                </div>
            </div>
            <div class="flex items-end gap-2 h-48">
                @foreach ($days as $d)
                    <div class="flex-1 flex flex-col items-center h-full">
                        <div class="flex-1 w-full flex items-end justify-center gap-1">
                            <div class="w-1/3 bg-emerald-500 rounded-t" style="height: {{ round($d['sales'] / $chartMax * 100) }}%" title="Sales {{ $ksh($d['sales']) }}"></div>
                            <div class="w-1/3 bg-red-400 rounded-t" style="height: {{ round($d['expenses'] / $chartMax * 100) }}%" title="Expenses {{ $ksh($d['expenses']) }}"></div>
                        </div>
                        <div class="text-[11px] text-gray-500 mt-1 whitespace-nowrap">{{ $d['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- LIVE STATUS --}}
        <div class="bg-white rounded-xl shadow p-4 space-y-3 text-sm">
            <h2 class="font-semibold">Right now</h2>
            <div class="flex justify-between"><span>Open tickets</span><span class="font-semibold">{{ $openTickets }}</span></div>
            <div>
                <div class="flex justify-between"><span>Open shifts</span><span class="font-semibold">{{ $openShifts->count() }}</span></div>
                @foreach ($openShifts as $s)
                    <div class="text-xs text-gray-500 pl-2">{{ $s->user->name }} since {{ $s->opened_at->format('H:i') }}</div>
                @endforeach
            </div>
            <div class="flex justify-between">
                <span>Printer queue</span>
                <span class="font-semibold">{{ $printPending }} waiting @if ($printFailed) &middot; <span class="text-red-600">{{ $printFailed }} failed</span> @endif</span>
            </div>
            <div class="pt-2 border-t flex flex-wrap gap-2">
                <a href="{{ route('pos.index') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded px-3 py-1">Open POS</a>
                <a href="{{ route('manage.menu') }}" class="border rounded px-3 py-1 hover:bg-gray-50">Edit menu</a>
            </div>
        </div>

        {{-- TOP ITEMS --}}
        <div class="bg-white rounded-xl shadow p-4 lg:col-span-3">
            <h2 class="font-semibold mb-3">Top items today</h2>
            @if ($topItems->isEmpty())
                <p class="text-sm text-gray-400 italic">No paid tickets yet today.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Item</th><th class="py-1 text-right">Qty</th><th class="py-1 text-right">Sales</th></tr></thead>
                    <tbody>
                        @foreach ($topItems as $t)
                            <tr class="border-b last:border-0"><td class="py-1">{{ $t->name }}</td><td class="py-1 text-right">{{ (int) $t->qty }}</td><td class="py-1 text-right">{{ $ksh($t->total) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
