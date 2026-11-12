@extends('layouts.manage')
@section('title', 'Purchase')
@php $ksh = fn ($v) => number_format((float) $v, 2); $st = $purchase->status(); @endphp

@section('content')
<a href="{{ route('manage.procurement', ['month' => $purchase->purchased_on->format('Y-m')]) }}" class="text-sm text-gray-500 hover:underline">&larr; Procurement</a>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-3">
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow p-5">
            <div class="flex justify-between items-start">
                <div>
                    <div class="text-lg font-semibold"><a href="{{ route('manage.procurement.supplier', $purchase->supplier) }}" class="hover:underline">{{ $purchase->supplier->name }}</a></div>
                    <div class="text-sm text-gray-500">{{ $purchase->purchased_on->format('D j M Y') }} @if ($purchase->reference) &middot; #{{ $purchase->reference }} @endif</div>
                </div>
                <span class="text-xs rounded px-2 py-0.5 {{ $st === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($st === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $st }}</span>
            </div>
            <p class="mt-3">{{ $purchase->description }}</p>
            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                <div><div class="text-xs text-gray-500">Total</div><div class="font-bold">{{ $ksh($purchase->total) }}</div></div>
                <div><div class="text-xs text-gray-500">Paid</div><div class="font-bold text-emerald-700">{{ $ksh($purchase->paidAmount()) }}</div></div>
                <div><div class="text-xs text-gray-500">Balance</div><div class="font-bold {{ $purchase->balance() > 0 ? 'text-red-700' : '' }}">{{ $ksh($purchase->balance()) }}</div></div>
            </div>
            @if ($purchase->due_on && $st !== 'paid')<p class="text-sm mt-3 {{ $purchase->isOverdue() ? 'text-red-700 font-semibold' : 'text-gray-500' }}">Due {{ $purchase->due_on->format('D j M Y') }}{{ $purchase->isOverdue() ? ' (overdue)' : '' }}</p>@endif
            <p class="text-xs text-gray-400 mt-2">Recorded by {{ $purchase->recorder->name ?? '?' }}</p>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-2">Payments</h2>
            @forelse ($purchase->payments->sortBy('paid_on') as $pay)
                <div class="flex justify-between items-center border-b last:border-0 py-1 text-sm">
                    <span>{{ $pay->paid_on->format('d M Y') }} &middot; {{ \App\Models\Expense::METHODS[$pay->method] ?? $pay->method }} @if ($pay->reference)<span class="text-gray-400">#{{ $pay->reference }}</span>@endif</span>
                    <span class="flex items-center gap-2">{{ $ksh($pay->amount) }}
                        <form method="POST" action="{{ route('manage.procurement.payments.destroy', $pay) }}" onsubmit="return confirm('Remove this payment?');">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline">remove</button></form>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No payments yet.</p>
            @endforelse

            @if ($purchase->balance() > 0)
            <form method="POST" action="{{ route('manage.procurement.payments.store', $purchase) }}" class="grid grid-cols-2 gap-2 mt-4 border-t pt-4">
                @csrf
                <div><label class="block text-xs text-gray-500">Amount</label><input type="number" name="amount" step="0.01" min="0.01" max="{{ $purchase->balance() }}" required value="{{ old('amount', $purchase->balance()) }}" class="w-full border rounded px-2 py-1 text-right"></div>
                <div><label class="block text-xs text-gray-500">Date</label><input type="date" name="paid_on" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
                <div><label class="block text-xs text-gray-500">Paid by</label><select name="method" class="w-full border rounded px-2 py-1">@foreach (\App\Models\Expense::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500">Ref</label><input name="reference" maxlength="40" class="w-full border rounded px-2 py-1"></div>
                <button class="col-span-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Record payment</button>
            </form>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <form method="POST" action="{{ route('manage.procurement.purchases.update', $purchase) }}" class="bg-white rounded-xl shadow p-5 space-y-3">
            @csrf @method('PATCH')
            <h2 class="font-semibold">Edit purchase</h2>
            <div><label class="block text-xs text-gray-500">Supplier</label><input name="supplier" list="supplier-names" required maxlength="80" value="{{ old('supplier', $purchase->supplier->name) }}" class="w-full border rounded px-2 py-1">
                <datalist id="supplier-names">@foreach ($suppliers as $n)<option value="{{ $n }}">@endforeach</datalist></div>
            <div><label class="block text-xs text-gray-500">What was bought</label><input name="description" required maxlength="200" value="{{ old('description', $purchase->description) }}" class="w-full border rounded px-2 py-1"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs text-gray-500">Date</label><input type="date" name="purchased_on" required max="{{ now()->toDateString() }}" value="{{ old('purchased_on', $purchase->purchased_on->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
                <div><label class="block text-xs text-gray-500">Total</label><input type="number" name="total" step="0.01" min="0.01" required value="{{ old('total', $purchase->total) }}" class="w-full border rounded px-2 py-1 text-right"></div>
                <div><label class="block text-xs text-gray-500">Invoice / ref</label><input name="reference" maxlength="40" value="{{ old('reference', $purchase->reference) }}" class="w-full border rounded px-2 py-1"></div>
                <div><label class="block text-xs text-gray-500">Due date</label><input type="date" name="due_on" value="{{ old('due_on', $purchase->due_on?->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
            </div>
            <button class="w-full bg-gray-800 text-white rounded py-2">Save changes</button>
        </form>
        <form method="POST" action="{{ route('manage.procurement.purchases.destroy', $purchase) }}" onsubmit="return confirm('Delete this purchase and all its payments? This cannot be undone.');">
            @csrf @method('DELETE')
            <button class="text-red-600 text-sm hover:underline">Delete this purchase</button>
        </form>
    </div>
</div>
@endsection
