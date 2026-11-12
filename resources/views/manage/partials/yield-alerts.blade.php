@php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.'); @endphp
@if (isset($yieldAlerts) && $yieldAlerts->isNotEmpty())
    <div class="bg-red-50 border border-red-300 rounded p-4 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h2 class="font-semibold text-red-800">&#9888; Raw material variances to explain ({{ $yieldAlerts->count() }})</h2>
            <a href="{{ route('stock.yield') }}" class="text-sm text-red-800 underline">Open yield report &rarr;</a>
        </div>
        <ul class="text-sm space-y-1 text-red-900">
            @foreach ($yieldAlerts->take(8) as $a)
                @php $u = $a->rule->stockItem->unit; @endphp
                <li>
                    <b>{{ $a->rule->stockItem->name }}</b> &middot; {{ $a->row->day->isToday() ? 'Today' : $a->row->day->format('D j M') }}:
                    used {{ $q($a->row->used) }} {{ $u }}, expected {{ $q($a->row->expected) }} {{ $u }}
                    @if ($a->row->pct !== null)<b>({{ sprintf('%+.0f', $a->row->pct) }}%)</b>@else<b>(nothing sold)</b>@endif
                </li>
            @endforeach
            @if ($yieldAlerts->count() > 8)<li class="text-red-700">and {{ $yieldAlerts->count() - 8 }} more&hellip;</li>@endif
        </ul>
    </div>
@endif
