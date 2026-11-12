@extends('layouts.manage')
@section('title', 'Yield & variance')
@php
    $q = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    $sign = fn ($v) => ($v > 0 ? '+' : '') . $q($v);
@endphp

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-3 -mt-2">
    <span class="text-gray-600 mr-2">{{ $from->format('D j M') }} &ndash; {{ $to->format('D j M Y') }}</span>
    @foreach ([7, 14, 30] as $n)
        <a href="{{ route('stock.yield', ['days' => $n]) }}" class="text-sm rounded-full px-3 py-1 border {{ $days === $n ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-gray-300 hover:bg-gray-50' }}">{{ $n }} days</a>
    @endforeach
    @if ($isManager)
        <a href="{{ route('stock.yield.rules.create') }}" class="ml-auto bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded px-3 py-1">+ Yield rule</a>
    @endif
</div>

<div class="bg-emerald-50 border border-emerald-200 rounded p-3 text-sm text-emerald-900 mb-4">
    <b>Used</b> comes from the daily stock sheet (opening + bought &minus; closing). <b>Expected</b> is what the day's paid sales should have used, from the yield rule.
    Days outside the tolerance are shown in <span class="text-red-700 font-semibold">red</span> and stay on the dashboard until the kitchen explains them.
</div>

@forelse ($report as $r)
    @php $rule = $r->rule; $unit = $rule->stockItem->unit; $isSales = $rule->basis === 'sales'; $t = $r->total; @endphp
    <div class="bg-white rounded shadow mb-5">
        <div class="px-4 py-3 border-b flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h2 class="font-semibold text-lg">{{ $rule->stockItem->name }}</h2>
            <span class="text-sm text-gray-600">
                {{ $q($rule->raw_qty) }} {{ $unit }} &rarr; {{ $isSales ? 'KSh ' . number_format((float) $rule->output) . ' in sales' : $q($rule->output) . ' portions' }}
                &middot; alert beyond &plusmn;{{ $rule->tolerance_pct }}%
            </span>
            @if ($isManager)
                <a href="{{ route('stock.yield.rules.edit', $rule) }}" class="ml-auto text-sm text-emerald-700 hover:underline">Edit rule</a>
            @endif
            <div class="w-full text-xs text-gray-500">
                {{ $rule->all_items ? 'All menu sales' : $rule->lines->map(fn ($l) => $l->item->name . ((float) $l->units != 1 ? ' x' . $q($l->units) : ''))->implode(', ') }}
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-3 py-2">Day</th>
                        <th class="px-3 py-2 text-right">{{ $isSales ? 'Sales KSh' : 'Portions sold' }}</th>
                        <th class="px-3 py-2 text-right">Expected {{ $unit }}</th>
                        <th class="px-3 py-2 text-right">Used {{ $unit }}</th>
                        <th class="px-3 py-2 text-right">Variance</th>
                        <th class="px-3 py-2">Kitchen explanation</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($r->rows->reverse() as $row)
                    <tr class="border-t {{ $row->flag ? ($row->note ? 'bg-amber-50' : 'bg-red-50') : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row->day->isToday() ? 'Today' : $row->day->format('D j M') }}</td>
                        <td class="px-3 py-2 text-right">{{ $isSales ? number_format($row->out) : $q($row->out) }}</td>
                        <td class="px-3 py-2 text-right">{{ $q($row->expected) }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($row->used === null)<span class="text-gray-400 text-xs">not counted</span>@else{{ $q($row->used) }}@endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap {{ $row->flag ? 'text-red-700 font-semibold' : 'text-gray-700' }}">
                            @if ($row->variance === null)
                                &ndash;
                            @else
                                {{ $sign($row->variance) }}
                                @if ($row->pct !== null)<span class="text-xs">({{ sprintf('%+.0f', $row->pct) }}%)</span>@elseif ($row->flag)<span class="text-xs">(nothing sold)</span>@endif
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($row->note)
                                {{ $row->note->reason }} <span class="text-xs text-gray-500">&mdash; {{ $row->note->explainer->name ?? '?' }}</span>
                            @elseif ($row->flag)
                                <form method="POST" action="{{ route('stock.yield.notes') }}" class="flex gap-1">
                                    @csrf
                                    <input type="hidden" name="stock_item_id" value="{{ $rule->stock_item_id }}">
                                    <input type="hidden" name="day" value="{{ $row->day->toDateString() }}">
                                    <input name="reason" required maxlength="500" placeholder="Why? e.g. spilled, staff meals, count error" class="flex-1 min-w-[12rem] border rounded px-2 py-1 text-sm">
                                    <button class="bg-red-600 hover:bg-red-700 text-white text-sm rounded px-3">Explain</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2">
                    <tr class="font-semibold">
                        <td class="px-3 py-2">Total <span class="font-normal text-xs text-gray-500">({{ $t->days }} counted day{{ $t->days === 1 ? '' : 's' }})</span></td>
                        <td class="px-3 py-2 text-right">{{ $isSales ? number_format($t->out) : $q($t->out) }}</td>
                        <td class="px-3 py-2 text-right">{{ $q($t->expected) }}</td>
                        <td class="px-3 py-2 text-right">{{ $q($t->used) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap {{ $t->flag ? 'text-red-700' : '' }}">
                            @if ($t->days){{ $sign($t->variance) }} @if ($t->pct !== null)<span class="text-xs">({{ sprintf('%+.0f', $t->pct) }}%)</span>@endif @else &ndash; @endif
                        </td>
                        <td class="px-3 py-2 font-normal text-sm">
                            @if ($t->perRaw !== null)
                                Got {{ $isSales ? 'KSh ' . number_format($t->perRaw) : $q($t->perRaw) }} per {{ $unit }}
                                <span class="text-gray-500">(target {{ $isSales ? 'KSh ' . number_format($t->targetPerRaw) : $q($t->targetPerRaw) }})</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@empty
    <div class="bg-white rounded shadow p-6 text-center text-gray-600">
        No yield rules yet.
        @if ($isManager)
            <a href="{{ route('stock.yield.rules.create') }}" class="text-emerald-700 underline">Add one</a>, for example Wheat flour: 2 kg &rarr; 30 chapatis.
        @else
            A manager needs to set them up.
        @endif
    </div>
@endforelse
@endsection
