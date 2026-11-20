@extends('layouts.manage')
@section('title', 'Meat check')
@php $n = fn ($v, $d = 1) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.'); @endphp
@section('content')
<div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
    <div class="flex items-center justify-between mb-3">
        <a href="{{ route('manage.reports.meat', ['to' => $from->copy()->subDay()->toDateString()]) }}" class="text-sm text-emerald-700 hover:underline">&larr; Earlier</a>
        <h2 class="font-semibold">Meat check &middot; {{ $from->format('j M') }} &ndash; {{ $to->format('j M Y') }}</h2>
        @if ($to->lt(today()))<a href="{{ route('manage.reports.meat', ['to' => $to->copy()->addDays(7)->toDateString()]) }}" class="text-sm text-emerald-700 hover:underline">Later &rarr;</a>@else<span></span>@endif
    </div>
    <div class="grid sm:grid-cols-2 gap-3 mb-4">
        @foreach ($totals as $m => $t)
            <div class="rounded-lg p-3 {{ $t->perKg !== null && $t->perKg < $min ? 'bg-red-50 border border-red-200' : 'bg-emerald-50 border border-emerald-200' }}">
                <div class="font-semibold">{{ $m }}: {{ $n($t->perKg) }} plates per kg <span class="text-xs text-gray-500">(target {{ $meats[$m]['target'] }})</span></div>
                <div class="text-sm">{{ $n($t->used, 2) }} kg used &rarr; {{ $n($t->plates, 0) }} plates sold over {{ $t->days }} counted day(s).
                    @if ($t->missing) <b>{{ $n($t->missing, 0) }} plates short, about KSh {{ number_format($t->missingCost) }} of meat.</b>@endif</div>
            </div>
        @endforeach
    </div>
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b"><tr>
            <th class="py-1">Day</th><th class="py-1">Meat</th><th class="py-1 text-right">Opening kg</th><th class="py-1 text-right">Received</th>
            <th class="py-1 text-right">Closing</th><th class="py-1 text-right">Used kg</th><th class="py-1 text-right">Plates sold</th>
            <th class="py-1 text-right">Plates/kg</th><th class="py-1 text-right">Short</th></tr></thead>
        <tbody>
        @foreach (array_reverse($rows, true) as $day => $meatsDay)
            @foreach ($meatsDay as $m => $r)
                <tr class="border-b {{ $r->flag ? 'bg-red-50' : '' }}">
                    <td class="py-1">@if ($loop->first){{ \Carbon\Carbon::parse($day)->format('D j M') }}@endif</td>
                    <td class="py-1">{{ $m }}</td>
                    <td class="py-1 text-right">{{ $n($r->opening, 2) }}</td>
                    <td class="py-1 text-right">{{ $r->received ? $n($r->received, 2) : '' }}</td>
                    <td class="py-1 text-right">{!! $r->counted ? e($n($r->closing, 2)) : '<span class="text-red-700">not counted</span>' !!}</td>
                    <td class="py-1 text-right">{{ $n($r->used, 2) }}</td>
                    <td class="py-1 text-right">{{ $n($r->plates, 0) }}</td>
                    <td class="py-1 text-right font-semibold {{ $r->perKg !== null && $r->perKg < $min ? 'text-red-700' : '' }}">{{ $n($r->perKg) }}</td>
                    <td class="py-1 text-right">{{ $r->missing ? $n($r->missing, 0) . ' (KSh ' . number_format($r->missingCost) . ')' : '' }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    <p class="text-xs text-gray-500 mt-3">Used = opening + received &minus; closing, from the daily stock count (Stock page). Record every meat delivery under Received and weigh what is left at closing each day.
        Plates sold = beef/nyama and pork dishes on bills. Red = under {{ $min }} plates per kg, or no closing count. Short = plates the meat used should have given at the target, valued at buying price.</p>
</div>
@endsection
