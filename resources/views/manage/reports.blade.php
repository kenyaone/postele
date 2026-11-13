@extends('layouts.manage')
@section('title', 'Reports')

@php
    $ksh = fn ($v) => number_format((float) $v, 2);
    $costs = $expenses + $purchases + $payroll; $net = $sales - $costs;
    $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $methodLabels = ['cash' => 'Cash', 'kk_stk' => 'M-Pesa STK', 'kk_till_auto' => 'M-Pesa Till', 'credit' => 'On credit (not paid yet)', 'kk_till_manual' => 'M-Pesa Till (manual)'];
    $presets = ['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month', 'last_month' => 'Last month'];
@endphp

@section('content')
    {{-- RANGE --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($presets as $k => $label)
                <a href="{{ route('manage.reports', ['preset' => $k]) }}"
                   class="text-sm rounded-full px-3 py-1 border {{ $preset === $k ? 'bg-emerald-600 text-white border-emerald-600' : 'hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('manage.reports') }}" class="flex items-end gap-2 ml-auto">
            <div><label class="block text-xs text-gray-500">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1"></div>
            <div><label class="block text-xs text-gray-500">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1"></div>
            <button class="bg-gray-800 text-white rounded px-3 py-1">Show</button>
        </form>
    </div>

    <p class="text-sm text-gray-500 -mt-3 mb-4">{{ $from->format('D j M Y') }} @if (! $from->eq($to)) &ndash; {{ $to->format('D j M Y') }} @endif</p>

    {{-- SUMMARY --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs uppercase text-gray-500">Money received</div><div class="text-2xl font-bold text-emerald-700">KSh {{ $ksh($sales) }}</div>@if ($onCredit > 0)<div class="text-xs text-orange-700 font-semibold">+ KSh {{ $ksh($onCredit) }} on credit, not paid yet</div>@endif</div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs uppercase text-gray-500">Tickets</div><div class="text-2xl font-bold">{{ $tickets }}</div><div class="text-xs text-gray-500">Avg KSh {{ $ksh($tickets ? $sales / $tickets : 0) }} @if ($voids) &middot; <span class="text-red-600">{{ $voids }} void</span> @endif</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs uppercase text-gray-500">Costs</div><div class="text-2xl font-bold text-red-700">KSh {{ $ksh($costs) }}</div><div class="text-xs text-gray-500">Expenses {{ $ksh($expenses) }} &middot; Purchases {{ $ksh($purchases) }} &middot; Payroll {{ $ksh($payroll) }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs uppercase text-gray-500">Net profit (sales &minus; costs)</div><div class="text-2xl font-bold {{ $net < 0 ? 'text-red-700' : '' }}">KSh {{ $ksh($net) }}</div><div class="text-xs text-gray-500">{{ $sales > 0 ? round($net / $sales * 100) . '% of sales' : '' }}</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="font-semibold mb-2">By payment method</h2>
            <table class="w-full text-sm">
                @forelse ($byMethod as $m => $r)
                    <tr class="border-b last:border-0"><td class="py-1">{{ $methodLabels[$m] ?? $m }} <span class="text-xs text-gray-400">({{ $r->n }})</span></td><td class="py-1 text-right">{{ $ksh($r->total) }}</td></tr>
                @empty
                    <tr><td class="text-gray-400 italic">No payments.</td></tr>
                @endforelse
            </table>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="font-semibold mb-2">By cashier</h2>
            <table class="w-full text-sm">
                @forelse ($byCashier as $c)
                    <tr class="border-b last:border-0"><td class="py-1">{{ $c->name }} <span class="text-xs text-gray-400">({{ $c->tickets }} tickets)</span></td><td class="py-1 text-right">{{ $ksh($c->total) }}</td></tr>
                @empty
                    <tr><td class="text-gray-400 italic">No sales.</td></tr>
                @endforelse
            </table>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="font-semibold mb-2">Expenses by category</h2>
            <table class="w-full text-sm">
                @forelse ($expByCat as $e)
                    <tr class="border-b last:border-0"><td class="py-1">{{ $e->category }}</td><td class="py-1 text-right">{{ $ksh($e->total) }}</td></tr>
                @empty
                    <tr><td class="text-gray-400 italic">No expenses.</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    {{-- DAILY --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6 overflow-x-auto">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-semibold">Day by day</h2>
            <a href="{{ route('manage.reports', $q + ['export' => 'daily']) }}" class="text-sm text-emerald-700 hover:underline">Download CSV</a>
        </div>
        @if (empty($daily))
            <p class="text-sm text-gray-400 italic">Nothing in this period.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Date</th><th class="py-1 text-right">Tickets</th><th class="py-1 text-right">Sales</th><th class="py-1 text-right">Expenses</th><th class="py-1 text-right">Purchases</th><th class="py-1 text-right">Payroll</th><th class="py-1 text-right">Net</th></tr></thead>
                <tbody>
                    @foreach ($daily as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-1 whitespace-nowrap">{{ $r['date']->format('D j M') }}</td>
                            <td class="py-1 text-right">{{ $r['tickets'] }}</td>
                            <td class="py-1 text-right">{{ $ksh($r['sales']) }}</td>
                            <td class="py-1 text-right">{{ $ksh($r['expenses']) }}</td>
                            <td class="py-1 text-right">{{ $ksh($r['purchases']) }}</td>
                            <td class="py-1 text-right">{{ $ksh($r['payroll']) }}</td>
                            <td class="py-1 text-right font-medium {{ $r['net'] < 0 ? 'text-red-700' : '' }}">{{ $ksh($r['net']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- WHAT SOLD --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="font-semibold mb-2">By menu category</h2>
            <table class="w-full text-sm">
                @forelse ($byCategory as $cat => $r)
                    <tr class="border-b last:border-0"><td class="py-1">{{ $cat }} <span class="text-xs text-gray-400">({{ $r['qty'] }})</span></td><td class="py-1 text-right">{{ $ksh($r['total']) }}</td></tr>
                @empty
                    <tr><td class="text-gray-400 italic">No items sold.</td></tr>
                @endforelse
            </table>
        </div>
        <div class="bg-white rounded-xl shadow p-4 lg:col-span-2 overflow-x-auto">
            <div class="flex justify-between items-center mb-2">
                <h2 class="font-semibold">By item</h2>
                <a href="{{ route('manage.reports', $q + ['export' => 'items']) }}" class="text-sm text-emerald-700 hover:underline">Download CSV</a>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Item</th><th class="py-1">Category</th><th class="py-1 text-right">Qty</th><th class="py-1 text-right">Sales</th></tr></thead>
                <tbody>
                    @forelse ($byItem as $r)
                        <tr class="border-b last:border-0"><td class="py-1">{{ $r->name }}</td><td class="py-1 text-gray-500">{{ $r->cat }}</td><td class="py-1 text-right">{{ (int) $r->qty }}</td><td class="py-1 text-right">{{ $ksh($r->total) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-gray-400 italic">No items sold.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400 mt-6">Net profit = sales &minus; expenses &minus; supplier purchases (by purchase date) &minus; staff payments (by date paid). Sales are money received.</p>
@endsection
