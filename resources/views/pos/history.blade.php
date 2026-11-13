@extends('layouts.manage')
@section('title', 'Order history')
@php
    $no = fn ($t) => '#' . $t->order_no;
    $pill = ['paid' => ['Paid', 'bg-emerald-100 text-emerald-800'], 'open' => ['Taking order', 'bg-sky-100 text-sky-800'], 'sent' => ['With kitchen', 'bg-amber-100 text-amber-900'], 'void' => ['Cancelled', 'bg-gray-200 text-gray-700'], 'credit' => ['On credit', 'bg-orange-100 text-orange-800'], 'unverified' => ['Not confirmed', 'bg-red-100 text-red-800']];
    $how = ['cash' => 'Cash', 'kk_stk' => 'M-Pesa', 'kk_till_auto' => 'M-Pesa', 'kk_till_manual' => 'M-Pesa', 'credit' => 'On account'];
@endphp

@section('content')
<form method="GET" action="{{ route('pos.history') }}" class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-4 mb-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 items-end -mt-2">
    <label class="text-xs text-gray-500 col-span-2 md:col-span-1">From<input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm"></label>
    <label class="text-xs text-gray-500 col-span-2 md:col-span-1">To<input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm"></label>
    <label class="text-xs text-gray-500">Status<select name="status" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm">
        @foreach (['all' => 'All', 'paid' => 'Paid', 'sent' => 'With kitchen', 'open' => 'Taking order', 'void' => 'Cancelled'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
    </select></label>
    @if ($hasChannel)
        <label class="text-xs text-gray-500">Type<select name="type" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm">
            @foreach (['all' => 'All', 'house' => 'Dine-in', 'glovo' => 'Glovo'] as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach
        </select></label>
    @endif
    <label class="text-xs text-gray-500">Paid by<select name="method" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm">
        @foreach (['all' => 'Any', 'cash' => 'Cash', 'mpesa' => 'M-Pesa', 'credit' => 'On account'] as $k => $v)<option value="{{ $k }}" @selected($method === $k)>{{ $v }}</option>@endforeach
    </select></label>
    @if ($seeAll)
        <label class="text-xs text-gray-500">Served by<select name="waiter" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm">
            <option value="0">Everyone</option>
            @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($waiter === $s->id)>{{ $s->name }}</option>@endforeach
        </select></label>
    @endif
    <label class="text-xs text-gray-500 col-span-2 md:col-span-1">Order # or M-Pesa code<input type="search" name="q" value="{{ $q }}" placeholder="e.g. 42 or UKC3..." class="mt-1 w-full border rounded-lg px-2 py-2 text-sm"></label>
    <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">Show</button>
</form>

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    <span class="rounded-full bg-espresso text-crema px-4 py-1.5 font-semibold">{{ $tickets->total() }} order{{ $tickets->total() === 1 ? '' : 's' }}</span>
    <span class="rounded-full bg-emerald-600 text-white px-4 py-1.5 font-semibold">Paid KSh {{ number_format($sales) }}</span>
    @foreach (['paid', 'sent', 'open', 'void'] as $s)
        @if (($byStatus[$s] ?? 0) > 0)<span class="rounded-full px-3 py-1.5 {{ $pill[$s][1] }}">{{ $pill[$s][0] }}: {{ $byStatus[$s] }}</span>@endif
    @endforeach
    @if ($q === '')<span class="text-gray-500 py-1.5">{{ $from->format('D j M') }} &ndash; {{ $to->format('D j M Y') }}</span>@endif
</div>

<div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
    @if ($tickets->isEmpty())
        <p class="p-6 text-center text-gray-500">No orders match these filters.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider">
                <tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Opened</th><th class="px-4 py-3">Served by</th><th class="px-4 py-3 text-right">Items</th><th class="px-4 py-3 text-right">Total KSh</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Paid by</th></tr>
            </thead>
            <tbody class="divide-y">
            @foreach ($tickets as $t)
                <tr class="hover:bg-amber-50/50">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('pos.tickets.show', $t) }}" class="font-bold text-espresso hover:underline">{{ $no($t) }}</a>
                        @if (($t->channel ?? 'house') === 'glovo')<span class="ml-1 text-[10px] font-bold rounded-full bg-orange-500 text-white px-1.5 py-0.5">GLOVO</span>@endif
                            <div class="text-xs text-gray-500 whitespace-normal max-w-xs mt-0.5">{{ $t->items->whereNull('void_at')->map(fn ($i) => $i->qty . ' x ' . ($i->item->name ?? 'Item'))->implode(', ') }}@if ($t->items->whereNotNull('void_at')->count())<span class="text-red-600"> (removed: {{ $t->items->whereNotNull('void_at')->map(fn ($i) => $i->qty . ' x ' . ($i->item->name ?? 'Item'))->implode(', ') }})</span>@endif</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $t->opened_at?->format('D j M H:i') }}</td>
                    <td class="px-4 py-3">{{ $t->opener->name ?? '?' }}</td>
                    <td class="px-4 py-3 text-right">{{ $t->items->sum('qty') }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($t->total) }}</td>
                    @php $pk = $t->status; if ($pk === 'paid') { $m = $t->payments->pluck('method'); if ($m->contains('kk_till_manual')) $pk = 'unverified'; elseif ($m->isNotEmpty() && $m->every(fn ($x) => $x === 'credit')) $pk = 'credit'; } @endphp
                    <td class="px-4 py-3"><span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $pill[$pk][1] ?? 'bg-gray-100' }}">{{ $pill[$pk][0] ?? $t->status }}</span></td>
                    <td class="px-4 py-3 text-xs">
                        @foreach ($t->payments as $p)
                            <div>{{ $how[$p->method] ?? $p->method }}@if ($p->kk_reference) <span class="font-mono font-semibold">{{ $p->kk_reference }}</span>@endif</div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
