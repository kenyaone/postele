@extends('layouts.manage')
@section('title', $customer->name)
@php $no = fn ($t) => '#' . str_pad($t->id, 5, '0', STR_PAD_LEFT); $ways = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank', 'other' => 'Other']; @endphp

@section('content')
<a href="{{ route('pos.debts') }}" class="inline-block text-sm text-roast hover:underline mb-3 -mt-2">&larr; All debts</a>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="rounded-2xl p-4 shadow {{ $customer->balance > 0 ? 'bg-gradient-to-br from-red-600 to-red-700 text-white' : 'bg-emerald-600 text-white' }}">
        <div class="text-xs uppercase tracking-wider opacity-80">Still owes</div>
        <div class="text-2xl font-extrabold">KSh {{ number_format(max(0, $customer->balance)) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5"><div class="text-xs uppercase tracking-wider text-gray-500">On account so far</div><div class="text-xl font-bold">KSh {{ number_format((float) $customer->owed_total) }}</div></div>
    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5"><div class="text-xs uppercase tracking-wider text-gray-500">Paid back</div><div class="text-xl font-bold text-emerald-700">KSh {{ number_format((float) $customer->paid_total) }}</div></div>
    <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-black/5"><div class="text-xs uppercase tracking-wider text-gray-500">{{ $customer->is_platform ? 'Commission kept' : 'Written off' }}</div><div class="text-xl font-bold">KSh {{ number_format((float) $customer->fees_total) }}</div></div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
            <h2 class="px-4 py-3 border-b font-semibold">Orders on account ({{ $sales->count() }})</h2>
            @if ($sales->isEmpty())<p class="p-4 text-sm text-gray-500">None yet.</p>@else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider"><tr><th class="px-4 py-2">When</th><th class="px-4 py-2">Order</th><th class="px-4 py-2">Dishes</th><th class="px-4 py-2 text-right">KSh</th><th class="px-4 py-2">Due</th><th class="px-4 py-2">By</th></tr></thead>
                <tbody class="divide-y">
                @foreach ($sales as $s)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $s->created_at->format('D j M H:i') }}</td>
                        <td class="px-4 py-2"><a href="{{ route('pos.tickets.show', $s->ticket_id) }}" class="font-bold hover:underline">{{ $s->ticket ? $no($s->ticket) : '#' . $s->ticket_id }}</a></td>
                        <td class="px-4 py-2 text-gray-700">{{ $s->ticket ? $s->ticket->items->map(fn ($l) => $l->qty . ' x ' . ($l->item->name ?? '?'))->implode(', ') : '' }}</td>
                        <td class="px-4 py-2 text-right font-bold tabular-nums">{{ number_format((float) $s->amount) }}</td>
                        <td class="px-4 py-2 whitespace-nowrap {{ $s->due_on && $s->due_on->isPast() && $customer->balance > 0 ? 'text-red-700 font-semibold' : 'text-gray-600' }}">{{ $s->due_on?->format('j M') ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $s->recorder->name ?? '?' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>@endif
        </div>
        <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 overflow-hidden">
            <h2 class="px-4 py-3 border-b font-semibold">Payments received ({{ $payments->count() }})</h2>
            @if ($payments->isEmpty())<p class="p-4 text-sm text-gray-500">None yet.</p>@else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider"><tr><th class="px-4 py-2">When</th><th class="px-4 py-2 text-right">KSh</th><th class="px-4 py-2 text-right">{{ $customer->is_platform ? 'Commission' : 'Written off' }}</th><th class="px-4 py-2">How</th><th class="px-4 py-2">Note</th><th class="px-4 py-2">By</th></tr></thead>
                <tbody class="divide-y">
                @foreach ($payments as $p)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $p->received_at->format('D j M H:i') }}</td>
                        <td class="px-4 py-2 text-right font-bold text-emerald-700 tabular-nums">{{ number_format((float) $p->amount) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ (float) $p->fees ? number_format((float) $p->fees) : '-' }}</td>
                        <td class="px-4 py-2">{{ $ways[$p->method] ?? $p->method }}@if ($p->reference) <span class="font-mono text-xs">{{ $p->reference }}</span>@endif</td>
                        <td class="px-4 py-2 text-gray-600">{{ $p->note }}</td>
                        <td class="px-4 py-2">{{ $p->recorder->name ?? '?' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>@endif
        </div>
    </div>

    <div>
        @if ($canRecord)
            <form method="POST" action="{{ route('pos.debts.pay', $customer) }}" class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-4 space-y-3 lg:sticky lg:top-4">
                @csrf
                <h2 class="font-semibold text-lg">{{ $customer->is_platform ? 'Record a Glovo payout' : 'Record a payment' }}</h2>
                <label class="block text-xs text-gray-500">Amount received (KSh)
                    <input type="number" name="amount" min="0" step="0.01" required value="{{ old('amount') }}" data-keypad="money" data-keypad-label="Amount received" class="mt-1 w-full border rounded-xl px-3 py-3 text-2xl font-bold text-right">
                </label>
                <label class="block text-xs text-gray-500">{{ $customer->is_platform ? 'Commission / fees kept by Glovo (KSh)' : 'Written off / discount (KSh, optional)' }}
                    <input type="number" name="fees" min="0" step="0.01" value="{{ old('fees') }}" data-keypad="money" data-keypad-label="Kept back" class="mt-1 w-full border rounded-xl px-3 py-2 text-right">
                </label>
                <div class="grid grid-cols-4 gap-1.5">
                    @foreach ($ways as $k => $v)
                        <label class="cursor-pointer"><input type="radio" name="method" value="{{ $k }}" class="peer sr-only" required @checked(old('method', $customer->is_platform ? 'bank' : 'cash') === $k)><span class="block rounded-xl ring-1 ring-roast/20 py-2 text-center text-sm font-semibold peer-checked:bg-espresso peer-checked:text-crema">{{ $v }}</span></label>
                    @endforeach
                </div>
                <input name="reference" maxlength="60" value="{{ old('reference') }}" placeholder="M-Pesa code / bank ref (optional)" class="w-full border rounded-xl px-3 py-2 text-sm uppercase">
                <input name="note" maxlength="255" value="{{ old('note') }}" placeholder="Note (optional)" class="w-full border rounded-xl px-3 py-2 text-sm">
                <button class="w-full rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3">Save payment</button>
                <p class="text-xs text-gray-500">Cash received here counts towards the cash drawer on the current shift.</p>
            </form>
        @else
            <p class="bg-white rounded-2xl shadow p-4 text-sm text-gray-600">A cashier or manager records repayments.</p>
        @endif
    </div>
</div>
@include('partials.touch')
@endsection
