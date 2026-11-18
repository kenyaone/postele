@extends('layouts.manage')
@section('title', 'Waiter performance')

@php
    $ksh = fn ($n) => 'KSh ' . number_format((float) $n);
    $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $presets = ['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month', 'last_month' => 'Last month'];
    $tot = ['bills' => 0, 'paid' => 0, 'sales' => 0, 'voids' => 0, 'open' => 0];
    foreach ($rows as $r) foreach ($tot as $k => $v) $tot[$k] += $r[$k];
    $bestAvg = collect($rows)->where('paid', '>', 0)->max('avg');
@endphp

@section('content')
    <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($presets as $k => $label)
                <a href="{{ route('manage.reports.staff', ['preset' => $k]) }}"
                   class="text-sm rounded-full px-3 py-1 border {{ $preset === $k ? 'bg-emerald-600 text-white border-emerald-600' : 'hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('manage.reports.staff') }}" class="flex items-end gap-2 ml-auto">
            <div><label class="block text-xs text-gray-500">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm"></div>
            <div><label class="block text-xs text-gray-500">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1 text-sm"></div>
            <button class="bg-gray-800 text-white text-sm rounded px-3 py-1.5">Show</button>
            <a href="{{ route('manage.reports.staff', $q + ['export' => 'csv']) }}" class="text-sm rounded px-3 py-1.5 border hover:bg-gray-50">Download</a>
        <a href="{{ route('manage.reports.staff', $q + ['export' => 'payout']) }}" class="text-sm rounded px-3 py-1.5 border border-emerald-600 text-emerald-800 hover:bg-emerald-50">Download pay sheet</a>
        </form>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Sales by waiters</div><div class="text-xl font-bold">{{ $ksh($tot['sales']) }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Paid bills</div><div class="text-xl font-bold">{{ $tot['paid'] }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Voided bills</div><div class="text-xl font-bold {{ $tot['voids'] ? 'text-red-700' : '' }}">{{ $tot['voids'] }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Bills still open</div><div class="text-xl font-bold {{ $tot['open'] ? 'text-amber-700' : '' }}">{{ $tot['open'] }}</div></div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">Waiter</th><th class="px-3 py-2 text-right">Paid bills</th><th class="px-3 py-2 text-right">Sales</th>
                    <th class="px-3 py-2 text-right">Average bill</th><th class="px-3 py-2 text-right">Cash</th><th class="px-3 py-2 text-right">M-Pesa</th>
                    <th class="px-3 py-2 text-right">Credit</th><th class="px-3 py-2 text-right">Voids</th><th class="px-3 py-2 text-right">Still open</th>
                    <th class="px-3 py-2 text-right">Time to pay</th><th class="px-3 py-2 text-right">Cash short / over</th>
                <th class="px-3 py-2 text-right bg-emerald-50">Collected</th><th class="px-3 py-2 text-right bg-emerald-50">Daily pay</th><th class="px-3 py-2 text-right bg-emerald-50">Commission</th><th class="px-3 py-2 text-right bg-emerald-50">Total pay</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-medium">{{ $r['name'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $r['paid'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $ksh($r['sales']) }}</td>
                        <td class="px-3 py-2 text-right {{ $r['paid'] && $r['avg'] == $bestAvg ? 'text-emerald-700 font-semibold' : '' }}">{{ $r['paid'] ? $ksh($r['avg']) : '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ $ksh($r['cash']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $ksh($r['mpesa']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $r['credit'] ? $ksh($r['credit']) : '-' }}</td>
                        <td class="px-3 py-2 text-right {{ $r['voidPct'] >= 10 ? 'text-red-700 font-semibold' : '' }}">{{ $r['voids'] }}@if ($r['voids']) <span class="text-xs">({{ $r['voidPct'] }}%)</span>@endif</td>
                        <td class="px-3 py-2 text-right {{ $r['open'] ? 'text-amber-700' : '' }}">{{ $r['open'] }}</td>
                        <td class="px-3 py-2 text-right">{{ $r['avgMins'] === null ? '-' : $r['avgMins'] . ' min' }}</td>
                        <td class="px-3 py-2 text-right {{ ($r['variance'] ?? 0) < 0 ? 'text-red-700 font-semibold' : '' }}">{{ $r['variance'] === null ? '-' : (($r['variance'] > 0 ? '+' : '') . $ksh($r['variance'])) }}</td>
                <td class="px-3 py-2 text-right bg-emerald-50/50">{{ $r['collected'] ? $ksh($r['collected']) : '-' }}</td>
                <td class="px-3 py-2 text-right bg-emerald-50/50">{{ $r['dailyPay'] ? $ksh($r['dailyPay']) : '-' }}</td>
                <td class="px-3 py-2 text-right bg-emerald-50/50">{{ $r['commission'] ? $ksh($r['commission']) : '-' }}</td>
                <td class="px-3 py-2 text-right bg-emerald-50/50 font-semibold">{{ $r['totalPay'] ? $ksh($r['totalPay']) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="px-3 py-6 text-center text-gray-500">No bills in these dates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
        Each bill counts for the waiter who opened it. Bills put on credit are <b>not</b> counted as paid; they are in the Credit column until the customer pays. <b>Average bill</b> in green is the highest, which usually means good suggesting of drinks and sides.
        <b>Voids</b> turn red at 10% or more of a waiter's bills; see the <a class="underline" href="{{ route('manage.voids') }}">Voids list</a> for reasons.
        <b>Time to pay</b> is from opening the bill to it being paid. <b>Cash short / over</b> compares the cash counted at shift close with what the system expected; red means money short.
    </p>
@php $wp = fn ($k) => number_format((float) \App\Services\WaiterPay::setting($k)); @endphp
<p class="text-xs text-gray-500 mt-2 leading-relaxed">
    <b>Waiter pay</b> (from {{ \Illuminate\Support\Carbon::parse(\App\Services\WaiterPay::setting('pay_start'))->format('j M Y') }}): <b>Collected</b> is cash and M-Pesa paid on the waiter's bills while their shift was open, plus old credit on the day it is repaid; unpaid, cancelled and credit bills count for nothing.
    Each day, collecting KSh {{ $wp('pay_threshold') }} (Saturday KSh {{ $wp('pay_threshold_sat') }}) earns KSh {{ $wp('pay_daily') }} daily pay, and {{ $wp('pay_comm_pct') }}% commission is paid on the part above KSh {{ $wp('pay_comm_from') }} (Saturday KSh {{ $wp('pay_comm_from_sat') }}) when the whole caf&eacute; collected at least KSh {{ $wp('pay_gate') }} (Saturday KSh {{ $wp('pay_gate_sat') }}).
    Change these in <a class="underline" href="{{ route('manage.settings') }}">Settings</a>. <b>Download pay sheet</b> gives one line per waiter per day for the dates shown.
</p>
@endsection
