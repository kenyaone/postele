@extends('layouts.manage')
@section('title', 'End my day')
@php $ksh = fn ($n) => 'KSh ' . number_format((float) $n); @endphp
@section('content')
<div class="max-w-3xl space-y-4">
    @if ($errors->any())<div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-3 text-red-800">{{ $errors->first() }}</div>@endif
    @if ($pending && $pending !== $day->toDateString())
        <a href="{{ route('pos.dayend', ['day' => $pending]) }}" class="block rounded-xl bg-red-600 text-white p-3 font-bold">&#9888; You have not closed {{ \Carbon\Carbon::parse($pending)->format('D j M') }} yet. Close it first &rarr;</a>
    @endif
    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5">
        <h2 class="text-lg font-bold">End of day &middot; {{ $day->format('l j F') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-sm">
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">Bills</div><div class="text-xl font-bold">{{ $s['bills']->count() }}</div></div>
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">Sales</div><div class="text-xl font-bold">{{ $ksh($s['sales']) }}</div></div>
            <div class="rounded-lg bg-emerald-50 p-3"><div class="text-gray-500">M-Pesa received</div><div class="text-xl font-bold">{{ $ksh($s['mpesa']) }}</div></div>
            <div class="rounded-lg bg-amber-50 p-3"><div class="text-gray-500">Cash you took</div><div class="text-xl font-bold">{{ $ksh($s['cash']) }}</div></div>
        </div>
        @if ($s['olderCount'])<p class="text-sm text-red-700 mt-3">&#9888; You also have {{ $s['olderCount'] }} unpaid bill(s) from earlier days ({{ $ksh($s['olderTotal']) }}). <a class="underline" href="{{ route('pos.clear') }}">Clear them</a>.</p>@endif
    </div>

    @if ($done)
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5 text-sm space-y-1">
            <div class="font-bold text-emerald-700">&#10003; Closed at {{ $done->closed_at->format('H:i') }}</div>
            <div>Cash recorded {{ $ksh($done->cash_expected) }} &middot; cash handed over {{ $ksh($done->cash_declared) }}
                @php $g = $done->shortfall(); @endphp
                @if ($g > 0.004)<b class="text-red-700">&middot; SHORT {{ $ksh($g) }}</b>@elseif ($g < -0.004)<b class="text-amber-700">&middot; over {{ $ksh(-$g) }}</b>@else<b class="text-emerald-700">&middot; matches</b>@endif</div>
            @if ($done->unpaid_count)<div>{{ $done->unpaid_count }} unpaid bill(s) explained, {{ $ksh($done->unpaid_amount) }}.</div>@endif
        </div>
    @else
    <form method="POST" action="{{ route('pos.dayend.store') }}" class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-5 space-y-4">
        @csrf
        <input type="hidden" name="day" value="{{ $day->toDateString() }}">
        @if ($s['unpaid']->count())
            <div>
                <div class="font-bold mb-1">{{ $s['unpaid']->count() }} bill(s) still unpaid &middot; {{ $ksh($s['unpaidTotal']) }}</div>
                <p class="text-xs text-gray-500 mb-2">Clear them on <a class="underline" href="{{ route('pos.clear') }}">Clear my bills</a> if they were paid. Otherwise say why for each one.</p>
                @foreach ($s['unpaid'] as $t)
                    <div class="border rounded-lg p-2 mb-2">
                        <div class="text-sm font-semibold">#{{ $t->order_no }} &middot; {{ $t->opened_at->format('H:i') }} &middot; {{ $ksh($t->balance) }}</div>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <select name="reason[{{ $t->id }}]" class="border rounded px-2 py-1 text-sm" required>
                                <option value="">Why unpaid?</option>
                                @foreach ($reasons as $k => $l)<option value="{{ $k }}" @selected(old('reason.' . $t->id) === $k)>{{ $l }}</option>@endforeach
                            </select>
                            <input name="why[{{ $t->id }}]" value="{{ old('why.' . $t->id) }}" maxlength="120" placeholder="Details (who, when they will pay)" class="flex-1 border rounded px-2 py-1 text-sm">
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <label class="block"><span class="font-bold">Cash you are handing over now (KSh)</span>
            <input type="number" name="cash_declared" step="1" min="0" required value="{{ old('cash_declared') }}" inputmode="numeric" class="block w-full border-2 rounded-lg px-3 py-2 text-2xl mt-1">
            <span class="text-xs text-gray-500">Count it. The POS recorded {{ $ksh($s['cash']) }} cash on your bills today.</span></label>
        <input name="note" maxlength="200" value="{{ old('note') }}" placeholder="Anything else to report (optional)" class="block w-full border rounded px-3 py-2 text-sm">
        <button class="w-full bg-gray-900 text-white rounded-xl py-3 text-lg font-bold">&#128274; Close my day</button>
    </form>
    @endif
</div>
@endsection
