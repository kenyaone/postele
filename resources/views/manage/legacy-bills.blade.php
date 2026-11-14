@extends('layouts.manage')
@section('title', 'Old bills (DineHQ)')

@php $ksh = fn ($n) => 'KSh ' . number_format((float) $n); @endphp

@section('content')
<p class="text-sm text-gray-600 mb-4">Bills left unpaid in the old DineHQ POS. They are kept apart from the new POS so sales are not counted twice. Clear each one as <b>Paid M-Pesa</b> (type the code from the customer's SMS or the paper bill), <b>Paid cash</b> (collected from the waiter) or <b>Write off</b> (with a reason).</p>

@if (session('ok'))<div class="mb-4 rounded-lg bg-emerald-50 text-emerald-900 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm font-semibold">{{ $errors->first() }}</div>@endif

<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Old bills imported</div><div class="text-xl font-bold">{{ $ksh($tot->all) }}</div></div>
    <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500">Still NOT PAID</div><div class="text-xl font-bold text-red-700">{{ $ksh($tot->owed) }} <span class="text-sm font-normal">({{ $tot->n }} bills)</span></div></div>
    <div class="bg-white rounded-xl shadow p-4 col-span-2 md:col-span-1"><div class="text-xs text-gray-500">Cleared</div><div class="text-xl font-bold text-emerald-700">{{ $ksh($tot->all - $tot->owed) }}</div></div>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-3 py-2">Waiter</th><th class="px-3 py-2 text-right">Bills</th><th class="px-3 py-2 text-right">Still NOT PAID</th><th class="px-3 py-2 text-right">M-Pesa</th><th class="px-3 py-2 text-right">Cash</th><th class="px-3 py-2 text-right">Written off</th></tr></thead>
        <tbody>
        @foreach ($byWaiter as $name => $w)
            <tr class="border-t"><td class="px-3 py-2 font-medium"><a class="underline" href="{{ route('manage.legacy', ['waiter' => $name, 'status' => 'unpaid']) }}">{{ $name }}</a></td><td class="px-3 py-2 text-right">{{ $w->bills }}</td><td class="px-3 py-2 text-right font-bold text-red-700">{{ $ksh($w->owed) }} <span class="text-xs font-normal">({{ $w->owedN }})</span></td><td class="px-3 py-2 text-right">{{ $ksh($w->mpesa) }}</td><td class="px-3 py-2 text-right">{{ $ksh($w->cash) }}</td><td class="px-3 py-2 text-right">{{ $ksh($w->off) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>

<form method="GET" class="bg-white rounded-xl shadow p-3 mb-3 flex flex-wrap gap-2 items-end text-sm">
    <label>Waiter<select name="waiter" class="block border rounded px-2 py-1"><option value="">Everyone</option>@foreach ($waiters as $w)<option @selected($waiter === $w)>{{ $w }}</option>@endforeach</select></label>
    <label>Show<select name="status" class="block border rounded px-2 py-1">@foreach (['unpaid' => 'NOT PAID', 'mpesa' => 'Paid M-Pesa', 'cash' => 'Paid cash', 'written_off' => 'Written off', 'all' => 'All'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach</select></label>
    <button class="bg-gray-800 text-white rounded px-3 py-1.5">Show</button>
    <span class="ml-auto text-gray-500">{{ $bills->count() }} bills &middot; {{ $ksh($bills->sum('amount')) }}</span>
</form>

<div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Waiter</th><th class="px-3 py-2">Items</th><th class="px-3 py-2 text-right">KSh</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Clear</th></tr></thead>
        <tbody>
        @forelse ($bills as $b)
            @php [$lbl, $cls] = \App\Models\LegacyBill::LABELS[$b->status] ?? [$b->status, 'bg-gray-100']; @endphp
            <tr class="border-t align-top">
                <td class="px-3 py-2 whitespace-nowrap"><b>{{ $b->bill_no }}</b><div class="text-xs text-gray-500">{{ $b->bill_date->format('D j M') }} {{ $b->bill_time }}</div></td>
                <td class="px-3 py-2">{{ $b->waiter }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $b->items }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ number_format($b->amount) }}</td>
                <td class="px-3 py-2"><span class="text-xs font-bold rounded-full px-2.5 py-1 whitespace-nowrap {{ $cls }}">{{ $lbl }}</span>
                    @if ($b->kk_reference)<div class="text-xs font-mono mt-1">{{ $b->kk_reference }} {!! $b->verified ? '<span class="text-emerald-700">&#10003; Kopo Kopo</span>' : '<span class="text-amber-700">check</span>' !!}</div>@endif
                    @if ($b->note)<div class="text-xs text-gray-500 mt-1">{{ $b->note }}</div>@endif
                </td>
                <td class="px-3 py-2">
                    @if ($b->status === 'unpaid')
                        <form method="POST" action="{{ route('manage.legacy.clear', $b) }}" class="flex flex-wrap gap-1 items-center">
                            @csrf
                            <input name="code" maxlength="12" placeholder="M-Pesa code" class="border rounded px-2 py-1 w-32 uppercase font-mono text-xs">
                            <button name="how" value="mpesa" class="text-xs rounded bg-emerald-600 text-white px-2 py-1">Paid M-Pesa</button>
                            <button name="how" value="cash" class="text-xs rounded border px-2 py-1" onclick="return confirm('Bill {{ $b->bill_no }} paid in cash? Collect KSh {{ number_format($b->amount) }} from {{ $b->waiter }}.')">Paid cash</button>
                            <input name="note" maxlength="200" placeholder="reason (write off)" class="border rounded px-2 py-1 w-36 text-xs">
                            <button name="how" value="written_off" class="text-xs rounded border border-red-300 text-red-700 px-2 py-1">Write off</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('manage.legacy.clear', $b) }}" onsubmit="return confirm('Put bill {{ $b->bill_no }} back to NOT PAID?')">@csrf<button name="how" value="unpaid" class="text-xs underline text-gray-500">Undo</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
