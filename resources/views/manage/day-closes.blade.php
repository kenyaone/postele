@extends('layouts.manage')
@section('title', 'Cash handovers')
@php $ksh = fn ($n) => 'KSh ' . number_format((float) $n); @endphp
@section('content')
<div class="space-y-4">
    @if ($missing->count())
        <div class="bg-red-50 ring-1 ring-red-200 rounded-xl p-4 text-sm">
            <div class="font-bold text-red-800 mb-1">&#9888; Days not closed</div>
            @foreach ($missing as $m)<div>{{ $users[$m->opened_by] ?? '?' }} &middot; {{ \Carbon\Carbon::parse($m->d)->format('D j M') }} ({{ $m->n }} bills)</div>@endforeach
            <div class="text-xs text-red-700 mt-1">They cannot start new bills until they close these days.</div>
        </div>
    @endif
    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">Waiters&rsquo; end-of-day cash handovers</h2>
        @if ($closes->isEmpty())
            <p class="text-sm text-gray-400 italic">No days closed yet. Waiters close their day from the POS (&ldquo;End my day&rdquo;).</p>
        @else
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Day</th><th class="py-1">Waiter</th><th class="py-1 text-right">Bills</th><th class="py-1 text-right">Sales</th><th class="py-1 text-right">M-Pesa</th><th class="py-1 text-right">Cash recorded</th><th class="py-1 text-right">Handed over</th><th class="py-1 text-right">Short / over</th><th class="py-1">Unpaid</th><th class="py-1">Closed</th></tr></thead>
            <tbody>
            @foreach ($closes as $c)
                @php $g = $c->shortfall(); @endphp
                <tr class="border-b last:border-0 align-top {{ $g > 0.004 ? 'bg-red-50' : '' }}">
                    <td class="py-1 whitespace-nowrap">{{ $c->day->format('D j M') }}</td>
                    <td class="py-1 font-medium">{{ $c->user->name ?? '?' }}</td>
                    <td class="py-1 text-right">{{ $c->bills }}</td>
                    <td class="py-1 text-right">{{ $ksh($c->sales) }}</td>
                    <td class="py-1 text-right">{{ $ksh($c->mpesa) }}</td>
                    <td class="py-1 text-right">{{ $ksh($c->cash_expected) }}</td>
                    <td class="py-1 text-right">{{ $ksh($c->cash_declared) }}</td>
                    <td class="py-1 text-right font-semibold {{ $g > 0.004 ? 'text-red-700' : ($g < -0.004 ? 'text-amber-700' : 'text-emerald-700') }}">{{ $g > 0.004 ? 'SHORT ' . $ksh($g) : ($g < -0.004 ? 'over ' . $ksh(-$g) : 'OK') }}</td>
                    <td class="py-1 text-xs">@if ($c->unpaid_count){{ $c->unpaid_count }} &middot; {{ $ksh($c->unpaid_amount) }}
                        @foreach ((array) $c->explanations as $e)<div class="text-gray-500">#{{ $e['bill'] ?? '' }} {{ $ksh($e['amount'] ?? 0) }}: {{ $reasons[$e['reason'] ?? ''] ?? '' }}{{ ! empty($e['note']) ? '  ' . $e['note'] : '' }}</div>@endforeach
                        @else &mdash; @endif
                        @if ($c->note)<div class="italic text-gray-500">&ldquo;{{ $c->note }}&rdquo;</div>@endif</td>
                    <td class="py-1 text-xs whitespace-nowrap">{{ $c->closed_at->format('j M H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
        <p class="text-xs text-gray-400 mt-2">Cash recorded = cash payments taken on the waiter&rsquo;s bills that day. Handed over = what the waiter counted and declared when closing.</p>
    </div>
</div>
@endsection
