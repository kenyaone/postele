@extends('layouts.manage')
@section('title', 'Edit expense')

@section('content')
    <form method="POST" action="{{ route('manage.expenses.update', $expense) }}" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-3">
        @csrf @method('PATCH')
        <div>
            <label class="block text-sm text-gray-600">Date</label>
            <input type="date" name="spent_on" required max="{{ now()->toDateString() }}" value="{{ old('spent_on', $expense->spent_on->toDateString()) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm text-gray-600">Category</label>
            <input name="category" list="expense-categories" required maxlength="40" value="{{ old('category', $expense->category) }}" class="w-full border rounded px-3 py-2">
            <datalist id="expense-categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
        </div>
        <div>
            <label class="block text-sm text-gray-600">Description</label>
            <input name="description" maxlength="160" value="{{ old('description', $expense->description) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm text-gray-600">Amount (KSh)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount', $expense->amount) }}" class="w-full border rounded px-3 py-2 text-right">
            </div>
            <div>
                <label class="block text-sm text-gray-600">Paid by</label>
                <select name="method" class="w-full border rounded px-3 py-2">
                    @foreach (\App\Models\Expense::METHODS as $k => $label)
                        <option value="{{ $k }}" {{ old('method', $expense->method) === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600">Ref</label>
                <input name="reference" maxlength="40" value="{{ old('reference', $expense->reference) }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div class="flex gap-3 pt-2">
            <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded px-6 py-2">Save</button>
            <a href="{{ route('manage.expenses.index', ['month' => $expense->spent_on->format('Y-m')]) }}" class="px-4 py-2 text-gray-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
