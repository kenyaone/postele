@extends('layouts.manage')
@section('title', $supplier->name)
@php $ksh = fn ($v) => number_format((float) $v, 2); @endphp

@section('content')
<a href="{{ route('manage.procurement') }}" class="text-sm text-gray-500 hover:underline">&larr; Procurement</a>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 bg-white rounded-xl shadow p-5 overflow-x-auto">
        <div class="flex gap-6 mb-4">
            <div><div class="text-xs text-gray-500">Bought (all time)</div><div class="text-xl font-bold">KSh {{ $ksh($bought) }}</div></div>
            <div><div class="text-xs text-gray-500">You owe</div><div class="text-xl font-bold {{ $owed > 0 ? 'text-red-700' : 'text-emerald-700' }}">KSh {{ $ksh($owed) }}</div></div>
        </div>
        @if ($purchases->isEmpty())
            <p class="text-sm text-gray-400 italic">No purchases from this supplier yet.</p>
        @else
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Date</th><th class="py-1">What</th><th class="py-1 text-right">Total</th><th class="py-1 text-right">Balance</th></tr></thead>
            <tbody>
            @foreach ($purchases as $p)
                <tr class="border-b last:border-0">
                    <td class="py-1 whitespace-nowrap">{{ $p->purchased_on->format('d M Y') }}</td>
                    <td class="py-1"><a href="{{ route('manage.procurement.purchase', $p) }}" class="hover:underline">{{ $p->description }}</a></td>
                    <td class="py-1 text-right">{{ $ksh($p->total) }}</td>
                    <td class="py-1 text-right {{ $p->balance() > 0 ? 'text-red-700 font-medium' : 'text-gray-400' }}">{{ $p->balance() > 0 ? $ksh($p->balance()) : 'paid' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    <form method="POST" action="{{ route('manage.procurement.suppliers.update', $supplier) }}" class="bg-white rounded-xl shadow p-5 space-y-3 h-fit">
        @csrf @method('PATCH')
        <h2 class="font-semibold">Supplier details</h2>
        <div><label class="block text-xs text-gray-500">Name</label><input name="name" required maxlength="80" value="{{ old('name', $supplier->name) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Phone</label><input name="phone" maxlength="30" value="{{ old('phone', $supplier->phone) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Notes</label><input name="notes" maxlength="200" value="{{ old('notes', $supplier->notes) }}" placeholder="e.g. delivers Tue/Fri" class="w-full border rounded px-2 py-1"></div>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" {{ $supplier->active ? 'checked' : '' }}> Active supplier</label>
        <button class="w-full bg-gray-800 text-white rounded py-2">Save</button>
    </form>
</div>
@endsection
