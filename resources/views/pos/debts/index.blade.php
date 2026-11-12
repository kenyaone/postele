@extends('layouts.manage')
@section('title', 'Debts')

@section('content')
<div class="flex flex-wrap items-center gap-3 mb-4 -mt-2">
    <div class="rounded-2xl bg-gradient-to-br from-red-600 to-red-700 text-white px-5 py-3 shadow">
        <div class="text-xs uppercase tracking-wider opacity-80">Owed to {{ config('pos.cafe_name') }}</div>
        <div class="text-2xl font-extrabold">KSh {{ number_format($totalOwed) }}</div>
    </div>
    <form method="GET" action="{{ route('pos.debts') }}" class="flex flex-wrap gap-2 sm:ml-auto">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search name or phone" class="border rounded-lg px-3 py-2 text-sm">
        <label class="flex items-center gap-1 text-sm text-gray-600"><input type="checkbox" name="all" value="1" @checked($all)> Show paid-up</label>
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">Show</button>
    </form>
</div>

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
    @forelse ($customers as $c)
        @php $late = isset($overdue[$c->id]) && $c->balance > 0; @endphp
        <a href="{{ route('pos.debts.show', $c) }}" class="block rounded-2xl bg-white p-4 shadow ring-1 hover:ring-2 hover:ring-saffron transition {{ $c->is_platform ? 'ring-orange-300' : ($late ? 'ring-red-300' : 'ring-black/5') }}">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-bold text-lg truncate">{!! $c->is_platform ? '&#128757; ' : '' !!}{{ $c->name }}</div>
                    <div class="text-xs text-gray-500">{{ $c->phone ?: ($c->is_platform ? 'Delivery platform' : 'No phone') }}</div>
                </div>
                @if ($late)<span class="shrink-0 text-[11px] font-bold rounded-full bg-red-100 text-red-800 px-2 py-0.5">OVERDUE</span>@endif
            </div>
            <div class="mt-3 flex items-end justify-between">
                <div class="text-xs text-gray-500">Last credit {{ $c->last_sale_at ? \Illuminate\Support\Carbon::parse($c->last_sale_at)->format('D j M') : '-' }}</div>
                <div class="text-right"><div class="text-[11px] uppercase tracking-wider text-gray-500">Owes</div><div class="font-display text-2xl font-extrabold {{ $c->balance > 0 ? 'text-red-700' : 'text-emerald-700' }}">KSh {{ number_format(max(0, $c->balance)) }}</div></div>
            </div>
        </a>
    @empty
        <p class="text-gray-500 col-span-full bg-white rounded-2xl p-6 text-center shadow">Nobody owes anything. &#10003; Put a bill on <b>Credit</b> from the POS payment screen to start a customer account.</p>
    @endforelse
</div>
@endsection
