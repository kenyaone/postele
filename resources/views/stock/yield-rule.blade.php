@extends('layouts.manage')
@section('title', $rule->exists ? 'Edit yield rule' : 'New yield rule')
@php $num = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<form method="POST" action="{{ $rule->exists ? route('stock.yield.rules.update', $rule) : route('stock.yield.rules.store') }}" class="space-y-4 max-w-3xl">
    @csrf
    @if ($rule->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-800 rounded p-3 text-sm">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="bg-white rounded shadow p-4 grid sm:grid-cols-2 gap-4">
        <label class="block">
            <span class="text-sm text-gray-600">Raw material (stock item)</span>
            <select name="stock_item_id" required class="w-full border rounded px-2 py-2">
                <option value="">Choose...</option>
                @foreach ($stockItems as $s)
                    <option value="{{ $s->id }}" @selected(old('stock_item_id', $rule->stock_item_id) == $s->id)>{{ $s->name }} ({{ $s->unit }})</option>
                @endforeach
            </select>
            @if ($stockItems->isEmpty())<span class="text-xs text-red-700">Add stock items first: Stock count &rarr; Stock items.</span>@endif
        </label>
        <label class="block">
            <span class="text-sm text-gray-600">Measure the yield by</span>
            <select name="basis" id="basis" class="w-full border rounded px-2 py-2">
                <option value="units" @selected(old('basis', $rule->basis) === 'units')>Portions made (e.g. chapatis, plates)</option>
                <option value="sales" @selected(old('basis', $rule->basis) === 'sales')>Sales value in KSh (e.g. cooking oil)</option>
            </select>
        </label>
        <label class="block">
            <span class="text-sm text-gray-600">Amount of raw material</span>
            <input type="number" step="any" min="0" name="raw_qty" required value="{{ old('raw_qty', $num($rule->raw_qty)) }}" placeholder="e.g. 2" class="w-full border rounded px-2 py-2">
            <span class="text-xs text-gray-500">In the stock item's unit (kg, litres...)</span>
        </label>
        <label class="block">
            <span class="text-sm text-gray-600" id="output-label">Should make (portions)</span>
            <input type="number" step="any" min="0" name="output" required value="{{ old('output', $num($rule->output)) }}" placeholder="e.g. 30" class="w-full border rounded px-2 py-2">
            <span class="text-xs text-gray-500" id="output-help">Example: 2 kg wheat flour should make 30 chapatis.</span>
        </label>
        <label class="block">
            <span class="text-sm text-gray-600">Alert when off by more than (%)</span>
            <input type="number" min="1" max="100" name="tolerance_pct" required value="{{ old('tolerance_pct', $rule->tolerance_pct ?? 10) }}" class="w-full border rounded px-2 py-2">
        </label>
        <label class="flex items-center gap-2 sm:mt-6">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $rule->active ?? true))> Active
        </label>
    </div>

    <div class="bg-white rounded shadow p-4">
        <h2 class="font-semibold">Menu items made from it</h2>
        <p class="text-sm text-gray-600 mb-2" id="units-help">Enter how many portions one sale of each item uses (e.g. Chapati Beans = 1, a plate with 2 chapatis = 2). Leave blank if the item does not use it.</p>
        <label id="all-wrap" class="flex items-center gap-2 mb-2 text-sm">
            <input type="hidden" name="all_items" value="0">
            <input type="checkbox" name="all_items" value="1" id="all-items" @checked(old('all_items', $rule->all_items))> Count <b>all</b> menu sales (oil used in almost everything)
        </label>
        <input type="search" id="item-filter" placeholder="Filter menu items..." class="w-full border rounded px-3 py-2 mb-2">
        <div class="max-h-96 overflow-y-auto border rounded divide-y" id="item-list">
            @foreach ($categories as $cat)
                @foreach ($cat->items as $it)
                    <label class="flex items-center gap-3 px-3 py-1.5 menu-row" data-name="{{ mb_strtolower($it->name) }}">
                        <input type="number" step="any" min="0" name="units[{{ $it->id }}]" value="{{ old('units.' . $it->id, isset($units[$it->id]) ? $num($units[$it->id]) : '') }}" class="w-20 border rounded px-2 py-1 text-right">
                        <span class="flex-1">{{ $it->name }}</span>
                        <span class="text-xs text-gray-500">{{ $cat->name }}</span>
                    </label>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="flex gap-3">
        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded px-5 py-2">Save rule</button>
        <a href="{{ route('stock.yield') }}" class="border rounded px-5 py-2 hover:bg-gray-50">Cancel</a>
    </div>
</form>

<script>
(function () {
    var basis = document.getElementById('basis'), filter = document.getElementById('item-filter');
    function sync() {
        var sales = basis.value === 'sales';
        document.getElementById('output-label').textContent = sales ? 'Should bring in (KSh of sales)' : 'Should make (portions)';
        document.getElementById('output-help').textContent = sales ? 'Example: 25 kg cooking oil should support KSh 3,000 of sales.' : 'Example: 2 kg wheat flour should make 30 chapatis.';
        document.getElementById('units-help').textContent = sales ? 'Tick all sales below, or enter 1 next to each menu item cooked with it.' : 'Enter how many portions one sale of each item uses (e.g. Chapati Beans = 1, a plate with 2 chapatis = 2). Leave blank if the item does not use it.';
        document.getElementById('all-wrap').style.display = sales ? '' : 'none';
    }
    basis.addEventListener('change', sync); sync();
    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        document.querySelectorAll('.menu-row').forEach(function (r) { r.style.display = !q || r.dataset.name.indexOf(q) !== -1 ? '' : 'none'; });
    });
})();
</script>
@endsection
