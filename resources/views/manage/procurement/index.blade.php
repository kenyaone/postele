@extends('layouts.manage')
@section('title', 'Procurement')
@php $ksh = fn ($v) => number_format((float) $v, 2); $badge = ['paid' => 'bg-emerald-100 text-emerald-800', 'partial' => 'bg-amber-100 text-amber-800', 'unpaid' => 'bg-red-100 text-red-800']; @endphp

@section('content')
<form method="POST" action="{{ route('manage.procurement.purchases.store') }}" class="bg-white rounded-xl shadow p-4 mb-6">
    @csrf
    <h2 class="font-semibold mb-3">Record a purchase</h2>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
        <div class="col-span-2"><label class="block text-xs text-gray-500">Supplier</label>
            <input name="supplier" list="supplier-names" required maxlength="80" value="{{ old('supplier') }}" placeholder="e.g. Eldoret Fresh Produce" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Date</label>
            <input type="date" name="purchased_on" required max="{{ now()->toDateString() }}" value="{{ old('purchased_on', now()->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Total (KSh)</label>
            <input type="number" name="total" step="0.01" min="0.01" required value="{{ old('total') }}" class="w-full border rounded px-2 py-1 text-right"></div>
        <div><label class="block text-xs text-gray-500">Invoice / ref</label>
            <input name="reference" maxlength="40" value="{{ old('reference') }}" placeholder="optional" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Paid?</label>
            <select name="paid_now" class="w-full border rounded px-2 py-1" onchange="document.getElementById('due-wrap').style.display = this.value === 'none' ? '' : 'none'">
                <option value="full" {{ old('paid_now', 'full') === 'full' ? 'selected' : '' }}>Paid in full now</option>
                <option value="none" {{ old('paid_now') === 'none' ? 'selected' : '' }}>On credit (pay later)</option>
            </select></div>
        <div class="col-span-2 md:col-span-3"><label class="block text-xs text-gray-500">What was bought</label>
            <input name="description" required maxlength="200" value="{{ old('description') }}" placeholder="e.g. 20kg potatoes, 5 trays eggs" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Paid by</label>
            <select name="method" class="w-full border rounded px-2 py-1">
                @foreach (\App\Models\Expense::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
            </select></div>
        <div id="due-wrap" style="{{ old('paid_now') === 'none' ? '' : 'display:none' }}"><label class="block text-xs text-gray-500">Due date</label>
            <input type="date" name="due_on" value="{{ old('due_on') }}" class="w-full border rounded px-2 py-1"></div>
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Add</button>
    </div>
    <datalist id="supplier-names">@foreach ($suppliers as $s)<option value="{{ $s->name }}">@endforeach</datalist>
</form>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
            <div class="flex items-center justify-between mb-3">
                <a href="{{ route('manage.procurement', ['month' => $prevMonth]) }}" class="text-sm text-emerald-700 hover:underline">&larr; Previous</a>
                <h2 class="font-semibold">{{ $monthLabel }} purchases &middot; KSh {{ $ksh($monthTotal) }}</h2>
                @if ($nextMonth <= now()->format('Y-m'))<a href="{{ route('manage.procurement', ['month' => $nextMonth]) }}" class="text-sm text-emerald-700 hover:underline">Next &rarr;</a>@else<span></span>@endif
            </div>
            @if ($purchases->isEmpty())
                <p class="text-sm text-gray-400 italic">No purchases this month.</p>
            @else
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Date</th><th class="py-1">Supplier</th><th class="py-1">What</th><th class="py-1 text-right">Total</th><th class="py-1 text-right">Status</th></tr></thead>
                <tbody>
                @foreach ($purchases as $p)
                    <tr class="border-b last:border-0 hover:bg-gray-50 cursor-pointer" onclick="location.href='{{ route('manage.procurement.purchase', $p) }}'">
                        <td class="py-1 whitespace-nowrap">{{ $p->purchased_on->format('d M') }}</td>
                        <td class="py-1">{{ $p->supplier->name }}</td>
                        <td class="py-1"><a href="{{ route('manage.procurement.purchase', $p) }}" class="hover:underline">{{ $p->description }}</a></td>
                        <td class="py-1 text-right whitespace-nowrap">{{ $ksh($p->total) }}</td>
                        <td class="py-1 text-right"><span class="text-xs rounded px-2 py-0.5 {{ $badge[$p->status()] }}">{{ $p->status() }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
            <h2 class="font-semibold mb-3">Unpaid bills &middot; <span class="text-red-700">KSh {{ $ksh($owedTotal) }} owed</span></h2>
            @if ($unpaid->isEmpty())
                <p class="text-sm text-gray-400 italic">Nothing owed to suppliers.</p>
            @else
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Bought</th><th class="py-1">Supplier</th><th class="py-1">Due</th><th class="py-1 text-right">Balance</th><th></th></tr></thead>
                <tbody>
                @foreach ($unpaid as $p)
                    <tr class="border-b last:border-0">
                        <td class="py-1 whitespace-nowrap">{{ $p->purchased_on->format('d M') }}</td>
                        <td class="py-1">{{ $p->supplier->name }} <span class="text-xs text-gray-400">{{ $p->description }}</span></td>
                        <td class="py-1 whitespace-nowrap {{ $p->isOverdue() ? 'text-red-700 font-semibold' : '' }}">{{ $p->due_on?->format('d M') ?? '-' }}{{ $p->isOverdue() ? ' (overdue)' : '' }}</td>
                        <td class="py-1 text-right font-medium">{{ $ksh($p->balance()) }}</td>
                        <td class="py-1 pl-2 text-right"><a href="{{ route('manage.procurement.purchase', $p) }}" class="text-emerald-700 hover:underline text-xs">Pay</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 h-fit">
        <h2 class="font-semibold mb-3">Suppliers</h2>
        <table class="w-full text-sm mb-4">
            @forelse ($suppliers as $s)
                <tr class="border-b last:border-0 {{ $s->active ? '' : 'text-gray-400' }}">
                    <td class="py-1"><a href="{{ route('manage.procurement.supplier', $s) }}" class="hover:underline">{{ $s->name }}</a> @if ($s->category)<span class="text-xs text-gray-400">{{ $s->category }}</span>@endif</td>
                    <td class="py-1 text-right text-xs {{ $s->owed > 0 ? 'text-red-700 font-semibold' : 'text-gray-400' }}">{{ $s->owed > 0 ? 'owe ' . $ksh($s->owed) : 'settled' }}</td>
                </tr>
            @empty
                <tr><td class="text-gray-400 italic">No suppliers yet. They are added automatically when you record a purchase.</td></tr>
            @endforelse
        </table>
        <form method="POST" action="{{ route('manage.procurement.suppliers.store') }}" class="space-y-2 border-t pt-3">
            @csrf
            <div class="text-xs text-gray-500">Add a supplier</div>
            <input name="name" required maxlength="80" placeholder="Name" class="w-full border rounded px-2 py-1 text-sm">
            <input name="phone" maxlength="30" placeholder="Phone (optional)" class="w-full border rounded px-2 py-1 text-sm">
            <button class="w-full border border-emerald-600 text-emerald-700 hover:bg-emerald-50 rounded py-1 text-sm">Add supplier</button>
        </form>
    </div>
</div>
@endsection
