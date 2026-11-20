@extends('layouts.manage')
@section('title', 'HR & Payroll')
@php $ksh = fn ($v) => number_format((float) $v, 2); @endphp

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow p-4 overflow-x-auto">
        <div class="flex items-center justify-between mb-3">
            <a href="{{ route('manage.hr', ['month' => $prev]) }}" class="text-sm text-emerald-700 hover:underline">&larr; Previous</a>
            <h2 class="font-semibold">Payroll for {{ $label }}</h2>
            @if ($next <= now()->format('Y-m'))<a href="{{ route('manage.hr', ['month' => $next]) }}" class="text-sm text-emerald-700 hover:underline">Next &rarr;</a>@else<span></span>@endif
        </div>
        <div class="mb-3"><a href="{{ route('manage.hr.attendance') }}" class="inline-block text-sm bg-emerald-700 text-white rounded px-3 py-1">&#128197; Daily attendance register</a> <a href="{{ route('clock') }}" target="_blank" class="inline-block text-sm border border-emerald-700 text-emerald-700 rounded px-3 py-1 ml-1">&#128247; Clock-in screen</a></div>
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-400 italic">No staff yet. Add your first staff member on the right.</p>
        @else
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr>
                <th class="py-1">Staff</th><th class="py-1 text-right">Pay earned</th><th class="py-1 text-right">Days absent</th>
                <th class="py-1 text-right">Absence</th><th class="py-1 text-right">Damages</th><th class="py-1 text-right">Net due</th>
                <th class="py-1 text-right">Paid</th><th class="py-1 text-right">Balance</th>
            </tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr class="border-b last:border-0 {{ $r->staff->active ? '' : 'text-gray-400' }}">
                    <td class="py-2"><a href="{{ route('manage.hr.staff', [$r->staff, 'month' => $period]) }}" class="font-medium hover:underline">{{ $r->staff->name }}</a>
                        <div class="text-xs text-gray-400">{{ $r->staff->position }}{{ $r->staff->pay_basis === 'waiter' ? '  KSh 240/day + commission' : '' }}</div></td>
                    <td class="py-2 text-right">{{ $ksh($r->base) }}</td>
                    <td class="py-2 text-right {{ $r->absent ? 'text-red-700 font-semibold' : 'text-gray-300' }}">{{ $r->absent }}</td>
                    <td class="py-2 text-right">{{ $r->absenceDeduction ? '' . $ksh($r->absenceDeduction) : '' }}</td>
                    <td class="py-2 text-right">{{ $r->damages ? '' . $ksh($r->damages) : '' }}</td>
                    <td class="py-2 text-right font-medium">{{ $ksh($r->due) }}</td>
                    <td class="py-2 text-right">{{ $ksh($r->paid) }}</td>
                    <td class="py-2 text-right font-semibold {{ $r->balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $ksh($r->balance) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="font-semibold border-t"><tr>
                <td class="py-2">Total</td><td class="py-2 text-right">{{ $ksh($rows->sum('base')) }}</td><td class="py-2 text-right">{{ $rows->sum('absent') }}</td>
                <td class="py-2 text-right">{{ $ksh($rows->sum('absenceDeduction')) }}</td><td class="py-2 text-right">{{ $ksh($rows->sum('damages')) }}</td>
                <td class="py-2 text-right">{{ $ksh($rows->sum('due')) }}</td><td class="py-2 text-right">{{ $ksh($rows->sum('paid')) }}</td><td class="py-2 text-right">{{ $ksh($rows->sum('balance')) }}</td>
            </tr></tfoot>
        </table>
        @endif
        <p class="text-xs text-gray-400 mt-3">Waiters earn KSh 240 per qualifying day plus commission, straight from the POS. Monthly staff lose salary &divide; 30 for each day marked Absent. Damages come off in the monthly instalment set on each person&rsquo;s page. Paid = salary + advances (bonuses are extra).</p>
    </div>

    <div class="space-y-6">
        <form method="POST" action="{{ route('manage.hr.staff.store') }}" class="bg-white rounded-xl shadow p-4 space-y-2">
            @csrf
            <h2 class="font-semibold">Add staff member</h2>
            <input name="name" required maxlength="80" placeholder="Full name" value="{{ old('name') }}" class="w-full border rounded px-2 py-1">
            <input name="position" maxlength="60" placeholder="Position (e.g. Cook, Waiter)" value="{{ old('position') }}" class="w-full border rounded px-2 py-1">
            <input name="phone" maxlength="30" placeholder="Phone" value="{{ old('phone') }}" class="w-full border rounded px-2 py-1">
            <div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs text-gray-500">Monthly salary</label><input type="number" name="monthly_salary" step="0.01" min="0" required value="{{ old('monthly_salary') }}" class="w-full border rounded px-2 py-1 text-right"></div>
                <div><label class="block text-xs text-gray-500">Start date</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full border rounded px-2 py-1"></div>
            </div>
            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Add</button>
            <p class="text-xs text-gray-400">This is the payroll record. To let someone sign in to the POS, also add them under Users.</p>
        </form>
        @if ($inactive->isNotEmpty())
            <div class="bg-white rounded-xl shadow p-4 text-sm">
                <h2 class="font-semibold mb-2">Former staff</h2>
                @foreach ($inactive as $s)<a href="{{ route('manage.hr.staff', $s) }}" class="block text-gray-500 hover:underline">{{ $s->name }}</a>@endforeach
            </div>
        @endif
    </div>
</div>
@endsection
