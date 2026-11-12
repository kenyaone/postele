@extends('layouts.manage')
@section('title', 'Stock items')

@section('content')
<a href="{{ route('stock.count') }}" class="text-sm text-gray-500 hover:underline">&larr; Closing stock count</a>

<form method="POST" action="{{ route('stock.items.store') }}" class="bg-white rounded-xl shadow p-4 my-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
    @csrf
    <div class="col-span-2"><label class="block text-xs text-gray-500">New item</label><input name="name" required maxlength="80" value="{{ old('name') }}" placeholder="e.g. Potatoes" class="w-full border rounded px-2 py-1"></div>
    <div><label class="block text-xs text-gray-500">Unit</label><input name="unit" list="units" required maxlength="20" value="{{ old('unit') }}" placeholder="kg" class="w-full border rounded px-2 py-1"></div>
    <div><label class="block text-xs text-gray-500">Reorder at</label><input type="number" name="reorder_level" step="0.01" min="0" required value="{{ old('reorder_level') }}" class="w-full border rounded px-2 py-1 text-right"></div>
    <div><label class="block text-xs text-gray-500">Buy up to</label><input type="number" name="par_level" step="0.01" min="0" value="{{ old('par_level') }}" placeholder="optional" class="w-full border rounded px-2 py-1 text-right"></div>
    <button class="col-span-2 md:col-span-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Add stock item</button>
    <datalist id="units">@foreach (\App\Models\StockItem::UNITS as $u)<option value="{{ $u }}">@endforeach</datalist>
</form>

<div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
    <p class="text-xs text-gray-500 mb-2"><b>Reorder at</b>: when the count is this or lower, the item goes on the shopping list. <b>Buy up to</b>: how much you like to hold, used to suggest how much to buy.</p>
    @if ($items->isEmpty())
        <p class="text-sm text-gray-400 italic">No stock items yet.</p>
    @else
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Item</th><th class="py-1 w-24">Unit</th><th class="py-1 w-28">Reorder at</th><th class="py-1 w-28">Buy up to</th><th class="py-1 w-16">Active</th><th></th></tr></thead>
        <tbody>
        @foreach ($items as $i)
            <tr class="border-b last:border-0 {{ $i->active ? '' : 'text-gray-400' }}">
                <td class="py-1 pr-2"><input form="si-{{ $i->id }}" name="name" required maxlength="80" value="{{ $i->name }}" class="w-full border rounded px-2 py-1"></td>
                <td class="py-1 pr-2"><input form="si-{{ $i->id }}" name="unit" list="units" required maxlength="20" value="{{ $i->unit }}" class="w-full border rounded px-2 py-1"></td>
                <td class="py-1 pr-2"><input form="si-{{ $i->id }}" type="number" name="reorder_level" step="0.01" min="0" required value="{{ (float) $i->reorder_level }}" class="w-full border rounded px-2 py-1 text-right"></td>
                <td class="py-1 pr-2"><input form="si-{{ $i->id }}" type="number" name="par_level" step="0.01" min="0" value="{{ $i->par_level !== null ? (float) $i->par_level : '' }}" class="w-full border rounded px-2 py-1 text-right"></td>
                <td class="py-1"><input form="si-{{ $i->id }}" type="hidden" name="active" value="0"><input form="si-{{ $i->id }}" type="checkbox" name="active" value="1" {{ $i->active ? 'checked' : '' }}></td>
                <td class="py-1 text-right">
                    <form method="POST" action="{{ route('stock.items.update', $i) }}" id="si-{{ $i->id }}">@csrf @method('PATCH')
                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded px-3 py-1">Save</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
