@extends('layouts.manage')
@section('title', 'Back-dated order')

@section('content')
<div class="flex flex-wrap gap-2 items-center mb-4 -mt-2">
    <a href="{{ route('pos.index') }}" class="border rounded px-3 py-1 text-sm bg-white hover:bg-gray-50">&larr; Back to POS</a>
</div>

<p class="mb-4 text-sm text-gray-600 max-w-2xl">For an order that really happened earlier but was not entered (POS down, written on paper, waiter forgot).
    It gets that day's order number, counts for the waiter you choose, and its payment is dated to that time, so daily sales and waiter pay land on the right day.
    <b>Nothing is printed.</b> Up to {{ $maxDays }} days back.</p>

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 text-sm p-3 font-semibold">&#9888; {{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('pos.backdate.store') }}" class="bg-white rounded shadow p-4 max-w-2xl grid grid-cols-1 sm:grid-cols-2 gap-3">
    @csrf
    <label class="text-sm font-semibold">Date
        <input type="date" name="date" required value="{{ old('date', now()->subDay()->toDateString()) }}" min="{{ now()->subDays($maxDays)->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 w-full border rounded px-2 py-2 font-normal">
    </label>
    <label class="text-sm font-semibold">Time
        <input type="time" name="time" required value="{{ old('time', '13:00') }}" class="mt-1 w-full border rounded px-2 py-2 font-normal">
    </label>
    <label class="text-sm font-semibold">Waiter who served it
        <select name="waiter_id" required class="mt-1 w-full border rounded px-2 py-2 font-normal">
            <option value="">Choose&hellip;</option>
            @foreach ($staff as $s)
                <option value="{{ $s->id }}" @selected((int) old('waiter_id') === $s->id)>{{ $s->name }}{{ $s->role === 'manager' ? ' (manager)' : '' }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Menu
        <select name="channel" class="mt-1 w-full border rounded px-2 py-2 font-normal">
            <option value="house" @selected(old('channel', 'house') === 'house')>Dine-in / takeaway</option>
            <option value="glovo" @selected(old('channel') === 'glovo')>Glovo</option>
        </select>
    </label>
    <label class="text-sm font-semibold sm:col-span-2">Why is it being entered late?
        <input name="reason" required maxlength="150" value="{{ old('reason') }}" placeholder="e.g. POS was offline, order written on paper" class="mt-1 w-full border rounded px-2 py-2 font-normal">
    </label>
    <div class="sm:col-span-2">
        <button class="rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3">Start back-dated order &rarr;</button>
        <span class="text-xs text-gray-500 ml-2">Next: add the dishes, press Send, then record the cash or M-Pesa.</span>
    </div>
</form>

@if ($recent->isNotEmpty())
<div class="bg-white rounded shadow mt-6">
    <div class="px-4 py-3 border-b font-semibold">Recent back-dated orders</div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Dated</th><th class="px-3 py-2">Waiter</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">KSh</th><th class="px-3 py-2">Note</th><th></th></tr></thead>
        <tbody>
        @foreach ($recent as $t)
            @php [$pl, $pc] = $t->payLabel(); @endphp
            <tr class="border-t">
                <td class="px-3 py-2 font-semibold">#{{ $t->order_no }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $t->opened_at?->format('D j M H:i') }}</td>
                <td class="px-3 py-2">{{ $t->opener->name ?? '?' }}</td>
                <td class="px-3 py-2"><span class="text-xs font-bold rounded-full px-2.5 py-1 {{ $pc }}">{{ $pl }}</span></td>
                <td class="px-3 py-2 text-right font-semibold">{{ number_format($t->total) }}</td>
                <td class="px-3 py-2 text-xs text-gray-500">{{ \Illuminate\Support\Str::after($t->notes, ': ') }}</td>
                <td class="px-3 py-2 text-right">@if (in_array($t->status, ['open', 'sent'], true))<a href="{{ route('pos.tickets.show', $t) }}" class="text-emerald-700 hover:underline">Open &rarr;</a>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
@endsection
