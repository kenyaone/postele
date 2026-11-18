@extends('layouts.manage')
@section('title', 'M-Pesa payments')
@php $no = fn ($t) => '#' . $t->order_no; @endphp

@section('content')
@if (session('ok'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-800 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>
@endif
@if ($errors->has('move'))
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 px-4 py-3 text-sm font-semibold">{{ $errors->first('move') }}</div>
@endif
<div class="flex flex-wrap items-center gap-2 mb-4 -mt-2">
    <form method="GET" action="{{ route('manage.mpesa') }}" class="flex flex-wrap gap-2 w-full">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search code, name or phone" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[12rem]">
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border rounded-lg px-3 py-2 text-sm">
        <select name="show" class="border rounded-lg px-3 py-2 text-sm">
            <option value="all" @selected($show === 'all')>All payments</option>
            <option value="linked" @selected($show === 'linked')>Linked to a bill</option>
            <option value="unlinked" @selected($show === 'unlinked')>Not linked to any bill</option>
        </select>
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">Show</button>
    </form>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-700 text-white p-4 shadow">
        <div class="text-xs uppercase tracking-wider opacity-80">{{ $q !== '' ? 'Matches' : ($date->isToday() ? 'Received today' : 'Received ' . $date->format('D j M')) }}</div>
        <div class="text-2xl font-extrabold mt-1">KSh {{ number_format($stats->total) }}</div>
        <div class="text-xs opacity-80">{{ $stats->count }} payment{{ $stats->count === 1 ? '' : 's' }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5">
        <div class="text-xs uppercase tracking-wider text-gray-500">Linked to bills</div>
        <div class="text-2xl font-extrabold mt-1 text-emerald-700">KSh {{ number_format($stats->linked) }}</div>
    </div>
    <div class="rounded-2xl p-4 shadow ring-1 {{ $stats->unlinkedCount ? 'bg-amber-50 ring-amber-300' : 'bg-white ring-black/5' }}">
        <div class="text-xs uppercase tracking-wider {{ $stats->unlinkedCount ? 'text-amber-800' : 'text-gray-500' }}">Not linked to any bill</div>
        <div class="text-2xl font-extrabold mt-1 {{ $stats->unlinkedCount ? 'text-amber-800' : 'text-gray-400' }}">KSh {{ number_format($stats->unlinked) }}</div>
        <div class="text-xs {{ $stats->unlinkedCount ? 'text-amber-800' : 'text-gray-500' }}">{{ $stats->unlinkedCount }} payment{{ $stats->unlinkedCount === 1 ? '' : 's' }}</div>
    </div>
    <a href="{{ route('pos.bills') }}" class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5 hover:ring-emerald-400 flex flex-col justify-center">
        <div class="text-sm font-semibold text-emerald-700">&#129534; Bills &rarr;</div>
        <div class="text-xs text-gray-500">Paid and not-paid bills</div>
    </a>
</div>

<div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
    @if ($rows->isEmpty())
        <p class="p-6 text-center text-gray-500">No M-Pesa payments {{ $q !== '' ? 'match that search' : 'on this day' }}.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left text-xs uppercase tracking-wider">
                <tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Paid by</th><th class="px-4 py-3">M-Pesa code</th><th class="px-4 py-3 text-right">KSh</th><th class="px-4 py-3">Bill</th></tr>
            </thead>
            <tbody class="divide-y">
            @foreach ($rows as $r)
                <tr class="{{ $r->status !== 'linked' ? 'bg-amber-50/60' : '' }} hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $r->time?->format($q !== '' ? 'D j M H:i' : 'H:i') }}</td>
                    <td class="px-4 py-3">
                        <div class="font-semibold">{{ $r->name !== '' ? $r->name : 'Name not sent' }}</div>
                        <div class="text-xs text-gray-500">{{ $r->phone !== '' ? $r->phone : 'Phone hidden by M-Pesa' }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono font-bold tracking-wide">{{ $r->code }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($r->amount, 2) }}</td>
                    <td class="px-4 py-3">
                        @if ($r->ticket)
                            <a href="{{ route('pos.tickets.show', $r->ticket) }}" class="inline-flex items-center gap-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-1 hover:bg-emerald-200">&#10003; {{ $no($r->ticket) }}</a>
                            <span class="text-xs text-gray-500">{{ $r->ticket->opener->name ?? '' }}</span>
                            @if ((float) $r->payment->amount - $r->amount > 0.001)<div class="text-xs text-red-700 font-semibold">Bill recorded KSh {{ number_format((float) $r->payment->amount, 2) }}</div>@endif
                        @elseif ($r->status === 'ignored')
                            <span class="text-xs rounded-full bg-gray-100 text-gray-600 px-2.5 py-1">Ignored</span>
                        @else
                            <span class="text-xs font-semibold rounded-full bg-amber-200 text-amber-900 px-2.5 py-1">&#9888; Not linked</span>
                        @endif
                    @if ($r->code)
                        @php $parts = $split[$r->code] ?? collect(); @endphp
                        @if ($parts->count() > 1)
                            <div class="text-xs text-gray-500 mt-1">Split: {{ $parts->map(fn ($p) => '#' . ($p->ticket?->order_no ?? '?') . ' KSh ' . number_format((float) $p->amount))->join(', ') }}</div>
                        @endif
                        <details class="mt-1" @if (old('code') === $r->code) open @endif>
                            <summary class="text-xs text-gray-500 cursor-pointer hover:text-emerald-700">{{ $parts->isEmpty() ? 'Put on bills' : 'Move / split across bills' }}&hellip;</summary>
                            <form method="POST" action="{{ route('manage.mpesa.split', $r->code) }}" class="mt-2 flex flex-col gap-1.5 w-60">
                                @csrf
                                <input type="hidden" name="code" value="{{ $r->code }}">
                                <input name="bills" required value="{{ old('code') === $r->code ? old('bills') : '' }}" placeholder="Bill numbers, e.g. 48, 49, 50" class="border rounded-lg px-2 py-1 text-sm">
                                <input name="reason" required maxlength="150" value="{{ old('code') === $r->code ? old('reason') : '' }}" placeholder="Why? e.g. one payment for 3 bills" class="border rounded-lg px-2 py-1 text-sm">
                                <p class="text-[11px] leading-snug text-gray-500">Each bill gets what it owes, in the order typed; anything left goes on the last bill. The bills it is on now are taken off and reopened if no longer paid.</p>
                                <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-3 py-1 text-xs font-semibold">Save</button>
                            </form>
                        </details>
                    @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
<p class="text-xs text-gray-500 mt-3">Every payment into Till {{ config('pos.till_number') }} that Kopo Kopo reported. &ldquo;Not linked&rdquo; means money arrived but no bill was cleared with it yet.</p>
@endsection
