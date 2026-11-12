@extends('layouts.manage')
@section('title', $staff->name)
@php $ksh = fn ($v) => number_format((float) $v, 2); $types = \App\Models\StaffPayment::TYPES; @endphp

@section('content')
<a href="{{ route('manage.hr', ['month' => $period]) }}" class="text-sm text-gray-500 hover:underline">&larr; HR &amp; Payroll</a>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
        <form method="POST" action="{{ route('manage.hr.payments.store', $staff) }}" class="bg-white rounded-xl shadow p-4">
            @csrf
            <div class="flex justify-between items-baseline mb-3">
                <h2 class="font-semibold">Record a payment</h2>
                <span class="text-sm {{ $balance > 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ \Carbon\Carbon::createFromFormat('Y-m-d', $period . '-01')->format('F Y') }}: {{ $balance > 0 ? 'KSh ' . $ksh($balance) . ' still owed' : 'salary fully paid' }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                <div><label class="block text-xs text-gray-500">For month</label><input type="month" name="period" required value="{{ old('period', $period) }}" class="w-full border rounded px-2 py-1"></div>
                <div><label class="block text-xs text-gray-500">Type</label><select name="type" class="w-full border rounded px-2 py-1">@foreach ($types as $k => $l)<option value="{{ $k }}" {{ old('type') === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500">Amount (KSh)</label><input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount', $balance > 0 ? $balance : '') }}" class="w-full border rounded px-2 py-1 text-right"></div>
                <div><label class="block text-xs text-gray-500">Paid on</label><input type="date" name="paid_on" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
                <div><label class="block text-xs text-gray-500">Paid by</label><select name="method" class="w-full border rounded px-2 py-1">@foreach (\App\Models\Expense::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500">Ref</label><input name="reference" maxlength="40" class="w-full border rounded px-2 py-1"></div>
                <div class="col-span-2"><label class="block text-xs text-gray-500">Notes</label><input name="notes" maxlength="160" placeholder="optional" class="w-full border rounded px-2 py-1"></div>
                <button class="col-span-2 md:col-span-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Record payment</button>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
            <h2 class="font-semibold mb-2">Payment history</h2>
            @if ($payments->isEmpty())
                <p class="text-sm text-gray-400 italic">No payments yet.</p>
            @else
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Paid on</th><th class="py-1">For</th><th class="py-1">Type</th><th class="py-1">How</th><th class="py-1 text-right">Amount</th><th></th></tr></thead>
                <tbody>
                @foreach ($payments as $p)
                    <tr class="border-b last:border-0">
                        <td class="py-1 whitespace-nowrap">{{ $p->paid_on->format('d M Y') }}</td>
                        <td class="py-1">{{ \Carbon\Carbon::createFromFormat('Y-m-d', $p->period . '-01')->format('M Y') }}</td>
                        <td class="py-1">{{ $types[$p->type] ?? $p->type }} @if ($p->notes)<span class="text-xs text-gray-400">{{ $p->notes }}</span>@endif</td>
                        <td class="py-1">{{ \App\Models\Expense::METHODS[$p->method] ?? $p->method }} @if ($p->reference)<span class="text-xs text-gray-400">#{{ $p->reference }}</span>@endif</td>
                        <td class="py-1 text-right font-medium">{{ $ksh($p->amount) }}</td>
                        <td class="py-1 pl-2 text-right"><form method="POST" action="{{ route('manage.hr.payments.destroy', $p) }}" onsubmit="return confirm('Remove this payment?');">@csrf @method('DELETE')<button class="text-red-600 text-xs hover:underline">remove</button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('manage.hr.staff.update', $staff) }}" class="bg-white rounded-xl shadow p-4 space-y-2 h-fit">
        @csrf @method('PATCH')
        <h2 class="font-semibold">Staff details</h2>
        <div><label class="block text-xs text-gray-500">Name</label><input name="name" required maxlength="80" value="{{ old('name', $staff->name) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Position</label><input name="position" maxlength="60" value="{{ old('position', $staff->position) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Phone</label><input name="phone" maxlength="30" value="{{ old('phone', $staff->phone) }}" class="w-full border rounded px-2 py-1"></div>
        <div><label class="block text-xs text-gray-500">Monthly salary</label><input type="number" name="monthly_salary" step="0.01" min="0" required value="{{ old('monthly_salary', (float) $staff->monthly_salary) }}" class="w-full border rounded px-2 py-1 text-right"></div>
        <div><label class="block text-xs text-gray-500">Start date</label><input type="date" name="start_date" value="{{ old('start_date', $staff->start_date?->toDateString()) }}" class="w-full border rounded px-2 py-1"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" {{ $staff->active ? 'checked' : '' }}> Currently employed</label>
        <button class="w-full bg-gray-800 text-white rounded py-2">Save</button>
    </form>
</div>
@endsection
