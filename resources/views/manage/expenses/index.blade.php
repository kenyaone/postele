@extends('layouts.manage')
@section('title', 'Expenses')

@section('content')
    {{-- ADD --}}
    <form method="POST" action="{{ route('manage.expenses.store') }}" class="bg-white rounded-xl shadow p-4 mb-6">
        @csrf
        <h2 class="font-semibold mb-3">Record an expense</h2>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500">Date</label>
                <input type="date" name="spent_on" required max="{{ now()->toDateString() }}" value="{{ old('spent_on', now()->toDateString()) }}" class="w-full border rounded px-2 py-1">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500">Category</label>
                <input name="category" list="expense-categories" required maxlength="40" value="{{ old('category') }}" placeholder="e.g. Gas / charcoal" class="w-full border rounded px-2 py-1">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Amount (KSh)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" class="w-full border rounded px-2 py-1 text-right">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Paid by</label>
                <select name="method" class="w-full border rounded px-2 py-1">
                    @foreach (\App\Models\Expense::METHODS as $k => $label)
                        <option value="{{ $k }}" {{ old('method', 'cash') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500">Ref / receipt no.</label>
                <input name="reference" maxlength="40" value="{{ old('reference') }}" placeholder="optional" class="w-full border rounded px-2 py-1">
            </div>
            <div class="col-span-2 md:col-span-5">
                <label class="block text-xs text-gray-500">Description</label>
                <input name="description" maxlength="160" value="{{ old('description') }}" placeholder="e.g. 13kg gas refill" class="w-full border rounded px-2 py-1">
            </div>
            <button class="col-span-2 md:col-span-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Add</button>
        </div>
        <datalist id="expense-categories">
            @foreach ($categories as $c)<option value="{{ $c }}">@endforeach
        </datalist>
    </form>

    {{-- MONTH NAV --}}
    <div class="flex items-center justify-between mb-3">
        <a href="{{ route('manage.expenses.index', ['month' => $prevMonth]) }}" class="text-sm text-emerald-700 hover:underline">&larr; Previous</a>
        <h2 class="font-semibold text-lg">{{ $monthLabel }} &middot; KSh {{ number_format($monthTotal, 2) }}</h2>
        @if ($nextMonth <= now()->format('Y-m'))
            <a href="{{ route('manage.expenses.index', ['month' => $nextMonth]) }}" class="text-sm text-emerald-700 hover:underline">Next &rarr;</a>
        @else
            <span></span>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- BY CATEGORY --}}
        <div class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">By category</h3>
            @if ($byCategory->isEmpty())
                <p class="text-sm text-gray-400 italic">Nothing recorded this month.</p>
            @else
                <table class="w-full text-sm">
                    @foreach ($byCategory as $c)
                        <tr class="border-b last:border-0">
                            <td class="py-1">
                                <a href="{{ route('manage.expenses.index', ['month' => $month, 'category' => $c->category]) }}" class="hover:underline {{ $category === $c->category ? 'font-semibold text-emerald-700' : '' }}">{{ $c->category }}</a>
                                <span class="text-xs text-gray-400">({{ $c->n }})</span>
                            </td>
                            <td class="py-1 text-right">{{ number_format($c->total, 2) }}</td>
                            <td class="py-1 pl-2 text-right text-xs text-gray-400 w-12">{{ $monthTotal > 0 ? round($c->total / $monthTotal * 100) : 0 }}%</td>
                        </tr>
                    @endforeach
                </table>
                @if ($category)
                    <a href="{{ route('manage.expenses.index', ['month' => $month]) }}" class="text-xs text-emerald-700 hover:underline mt-2 inline-block">Show all categories</a>
                @endif
            @endif
        </div>

        {{-- LIST --}}
        <div class="bg-white rounded-xl shadow p-4 lg:col-span-2 overflow-x-auto">
            <h3 class="font-semibold mb-2">{{ $category ? $category : 'All expenses' }} <span class="text-gray-400 font-normal text-sm">({{ $expenses->count() }})</span></h3>
            @if ($expenses->isEmpty())
                <p class="text-sm text-gray-400 italic">No expenses.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b">
                        <tr><th class="py-1">Date</th><th class="py-1">Category</th><th class="py-1">Description</th><th class="py-1">Paid</th><th class="py-1 text-right">Amount</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $e)
                            <tr class="border-b last:border-0 align-top">
                                <td class="py-1 whitespace-nowrap">{{ $e->spent_on->format('d M') }}</td>
                                <td class="py-1">{{ $e->category }}</td>
                                <td class="py-1">{{ $e->description }} @if ($e->reference)<span class="text-xs text-gray-400">#{{ $e->reference }}</span>@endif
                                    <div class="text-xs text-gray-400">by {{ $e->recorder->name ?? '?' }}</div></td>
                                <td class="py-1">{{ \App\Models\Expense::METHODS[$e->method] ?? $e->method }}</td>
                                <td class="py-1 text-right font-medium whitespace-nowrap">{{ number_format($e->amount, 2) }}</td>
                                <td class="py-1 pl-2 text-right whitespace-nowrap">
                                    <a href="{{ route('manage.expenses.edit', $e) }}" class="text-emerald-700 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('manage.expenses.destroy', $e) }}" class="inline" onsubmit="return confirm('Delete this expense?');">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline text-xs ml-1">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
