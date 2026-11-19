@extends('layouts.manage')
@section('title', 'Slips not printed')

@section('content')
<div class="max-w-3xl">
<p class="mb-4 text-sm text-gray-600">These slips were made while the printer (or the counter PC) was off, so they never printed.
    Tick the ones still needed and press <b>Reprint ticked</b>. The rest are skipped. Kitchen tickets for orders that are already paid or cancelled are left unticked.</p>

@if ($jobs->isEmpty())
    <div class="bg-white rounded-2xl shadow p-5 text-sm">Nothing waiting. &#10003;</div>
@else
<form method="POST" action="{{ route('manage.printers.missed.reprint') }}" class="bg-white rounded-2xl shadow divide-y">
    @csrf
    @foreach ($jobs as $j)
        @php
            $t = $j->ticket_id ? ($tickets[$j->ticket_id] ?? null) : null;
            $open = $t && in_array($t->status, ['open', 'sent'], true);
            $label = ['kot' => 'Kitchen ticket', 'bill' => 'Bill', 'receipt' => 'Receipt'][$j->kind] ?? ucfirst((string) $j->kind ?: $j->printer);
            $tick = $j->kind === 'kot' ? $open : ($j->kind === 'bill' ? $open : false);
        @endphp
        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-green-50">
            <input type="checkbox" name="jobs[]" value="{{ $j->id }}" class="w-5 h-5 accent-green-600" @checked($tick)>
            <span class="flex-1 min-w-0">
                <b>{{ $label }}</b>@if ($t) &middot; #{{ $t->order_no }}@endif
                <span class="block text-xs text-gray-500">{{ $j->created_at->format('D H:i') }} &middot; {{ $j->target->name ?? $j->printer }}@if ($t) &middot; order is {{ $t->status === 'paid' ? 'paid' : ($t->status === 'void' ? 'cancelled' : 'still open') }}@endif</span>
            </span>
        </label>
    @endforeach
    <div class="px-4 py-3 flex flex-wrap gap-3 items-center">
        <button class="rounded-xl bg-green-700 hover:bg-green-800 text-white font-bold px-6 py-3">Reprint ticked</button>
        <span class="text-xs text-gray-500">Unticked slips are skipped and will not print later.</span>
    </div>
</form>
@endif
</div>
@endsection
