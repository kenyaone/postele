@extends('layouts.manage')
@section('title', 'Attendance')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('manage.hr.attendance.store') }}" class="lg:col-span-2 bg-white rounded-xl shadow p-4 overflow-x-auto">
        @csrf
        <input type="hidden" name="day" value="{{ $day->toDateString() }}">
        <div class="flex items-center justify-between mb-3">
            <a href="{{ route('manage.hr.attendance', ['date' => $day->copy()->subDay()->toDateString()]) }}" class="text-sm text-emerald-700 hover:underline">&larr; Previous day</a>
            <h2 class="font-semibold">Attendance &middot; {{ $day->format('D j M Y') }}</h2>
            @if ($day->lt(today()))<a href="{{ route('manage.hr.attendance', ['date' => $day->copy()->addDay()->toDateString()]) }}" class="text-sm text-emerald-700 hover:underline">Next day &rarr;</a>@else<span></span>@endif
        </div>
        @if (! $saved)
            <p class="text-xs bg-amber-50 text-amber-800 rounded px-3 py-2 mb-3">Not saved yet for this day. Waiters who served bills are ticked Present and waiters with no bills Absent; kitchen and other staff start as Present. Correct anyone who was off, on leave, sick or absent, then save.</p>
        @endif
        @if ($staff->isEmpty())
            <p class="text-sm text-gray-400 italic">No active staff. Add them under HR &amp; Payroll.</p>
        @else
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b">
                <tr><th class="py-1">Staff</th>
                    @foreach ($statuses as $label)<th class="py-1 text-center">{{ $label }}</th>@endforeach
                    <th class="py-1">Note</th></tr>
            </thead>
            <tbody>
            @foreach ($staff as $s)
                @php
                    $bills = $s->user_id ? ($worked[$s->user_id] ?? 0) : 0;
                    $cur = $marks[$s->id]->status ?? ($s->pay_basis === 'waiter' ? ($bills ? 'present' : 'absent') : 'present');
                @endphp
                <tr class="border-b last:border-0">
                    <td class="py-2 pr-2">
                        <div class="font-medium">{{ $s->name }}</div>
                        <div class="text-xs text-gray-400">{{ $s->position }}@if ($s->user_id) &middot; <span class="{{ $bills ? 'text-emerald-700' : '' }}">{{ $bills }} bill(s)</span>@endif</div>
                    </td>
                    @foreach ($statuses as $key => $label)
                        <td class="py-2 text-center"><input type="radio" class="w-5 h-5" name="status[{{ $s->id }}]" value="{{ $key }}" @checked($cur === $key)></td>
                    @endforeach
                    <td class="py-2"><input name="note[{{ $s->id }}]" value="{{ $marks[$s->id]->note ?? '' }}" maxlength="120" class="w-full border rounded px-2 py-1"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <button class="mt-4 bg-gray-800 text-white rounded px-4 py-2">Save attendance</button>
        @endif
        <p class="text-xs text-gray-400 mt-3">Absent = did not come without permission: monthly-paid staff lose a day&rsquo;s pay (salary &divide; 30). Off day, leave and sick are not deducted. Waiters are paid only for days they work, so marking them is for the record.</p>
    </form>

    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">{{ $day->format('F Y') }} so far</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Staff</th><th class="py-1 text-right">Present</th><th class="py-1 text-right">Absent</th><th class="py-1 text-right">Off/leave/sick</th></tr></thead>
            <tbody>
            @foreach ($staff as $s)
                @php $c = $month[$s->id] ?? collect(); @endphp
                <tr class="border-b last:border-0">
                    <td class="py-1"><a href="{{ route('manage.hr.staff', $s) }}" class="hover:underline">{{ $s->name }}</a></td>
                    <td class="py-1 text-right">{{ $c['present'] ?? 0 }}</td>
                    <td class="py-1 text-right {{ ($c['absent'] ?? 0) ? 'text-red-700 font-semibold' : '' }}">{{ $c['absent'] ?? 0 }}</td>
                    <td class="py-1 text-right">{{ ($c['off'] ?? 0) + ($c['leave'] ?? 0) + ($c['sick'] ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
