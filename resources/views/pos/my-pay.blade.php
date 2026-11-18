@extends('layouts.manage')
@section('title', 'My pay')

@php
    $ksh = fn ($n) => 'KSh ' . number_format((float) $n, fmod((float) $n, 1) ? 2 : 0);
    $wp = fn ($k) => number_format((float) \App\Services\WaiterPay::setting($k));
@endphp

@section('content')
<div class="max-w-3xl space-y-5">
@if (! $isWaiter)
    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5 text-sm">
        Waiter pay is only for waiters. Managers see everyone's pay in the
        @if (auth()->user()->role === 'manager')<a href="{{ route('manage.reports.staff') }}" class="font-semibold underline">Waiters report</a>@else Waiters report @endif.
    </div>
@else
    <div class="bg-emerald-900 text-emerald-50 rounded-2xl shadow p-5">
        <div class="text-xs font-bold uppercase tracking-wider text-emerald-200">Today &middot; {{ now()->format('D j M') }}</div>
        <div class="mt-1 text-3xl font-extrabold">{{ $ksh($today['collected']) }} <span class="text-base font-semibold text-emerald-200">collected</span></div>
        <div class="mt-1 text-sm">{{ $today['next'] }}</div>
    </div>

    @foreach ($months as $m)
    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
        <div class="px-4 py-3 flex flex-wrap items-baseline justify-between gap-2 border-b">
            <h2 class="font-bold">{{ $m['label'] }}</h2>
            <div class="text-sm">Total pay <b class="text-lg">{{ $ksh($m['total']) }}</b></div>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr><th class="px-3 py-2">Day</th><th class="px-3 py-2 text-right">Collected</th><th class="px-3 py-2 text-right">Daily pay</th><th class="px-3 py-2 text-right">Commission</th><th class="px-3 py-2 text-right">Total</th></tr>
            </thead>
            <tbody>
            @forelse ($m['days'] as $d)
                <tr class="border-t align-top">
                    <td class="px-3 py-2 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D j M') }}</td>
                    <td class="px-3 py-2 text-right">{{ $ksh($d['collected']) }}</td>
                    <td class="px-3 py-2 text-right">
                        {{ $d['daily'] ? $ksh($d['daily']) : '-' }}
                        @if (! $d['active'])<div class="text-xs text-gray-400">before pay started</div>@endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{ $d['commission'] ? $ksh($d['commission']) : '-' }}
                        @if ($d['active'] && $d['collected'] > $d['from'] && ! $d['gateMet'])
                            <div class="text-xs text-amber-700">Caf&eacute; collected {{ $ksh($d['cafe']) }}, below {{ $ksh($d['gate']) }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right font-semibold">{{ $ksh($d['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No money collected on your bills in this month.</td></tr>
            @endforelse
            </tbody>
            @if (count($m['days']))
            <tfoot class="bg-gray-50 font-semibold border-t">
                <tr>
                    <td class="px-3 py-2">{{ count($m['days']) }} day{{ count($m['days']) === 1 ? '' : 's' }}</td>
                    <td class="px-3 py-2 text-right">{{ $ksh($m['collected']) }}</td>
                    <td class="px-3 py-2 text-right">{{ $ksh($m['daily']) }}</td>
                    <td class="px-3 py-2 text-right">{{ $ksh($m['commission']) }}</td>
                    <td class="px-3 py-2 text-right">{{ $ksh($m['total']) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>
    @endforeach

    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5 text-sm text-gray-600 space-y-1.5">
        <p class="font-semibold text-espresso">How your pay works</p>
        <p><b>Collected</b> is money paid on your bills, cash or M-Pesa, while your shift is open, plus old credit on the day the customer repays it. Unpaid, cancelled and credit bills count for nothing.</p>
        <p><b>Daily pay:</b> collect KSh {{ $wp('pay_threshold') }} in a day (Saturday KSh {{ $wp('pay_threshold_sat') }}) and you earn KSh {{ $wp('pay_daily') }}.</p>
        <p><b>Commission:</b> {{ $wp('pay_comm_pct') }}% of what you collect above KSh {{ $wp('pay_comm_from') }} (Saturday KSh {{ $wp('pay_comm_from_sat') }}), paid when the whole caf&eacute; collects at least KSh {{ $wp('pay_gate') }} that day (Saturday KSh {{ $wp('pay_gate_sat') }}).</p>
        <p>Close your shift when you leave. Figures update as payments come in; the manager's pay sheet is final.</p>
    </div>
@endif
</div>
@endsection
