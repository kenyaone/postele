@extends('layouts.manage')
@section('title', 'Activity log')
@php
    $verb = ['GET' => 'Viewed', 'POST' => 'Saved', 'PATCH' => 'Updated', 'PUT' => 'Updated', 'DELETE' => 'Deleted'];
    $device = function ($a) { $a = (string) $a; return str_contains($a, 'Android') ? 'Android' : (str_contains($a, 'iPhone') ? 'iPhone' : (str_contains($a, 'Windows') ? 'Windows PC' : (str_contains($a, 'CrOS') ? 'Chromebook' : (str_contains($a, 'Mac') ? 'Mac' : 'Other')))); };
@endphp
@section('content')
<div class="space-y-4">
    <form method="GET" class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-2 items-end">
        <label class="text-xs text-gray-500">Day<input type="date" name="date" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}" class="block border rounded px-2 py-1"></label>
        <label class="text-xs text-gray-500">Person<select name="user" class="block border rounded px-2 py-1"><option value="">Everyone</option>@foreach ($users as $id => $nm)<option value="{{ $id }}" @selected($userId == $id)>{{ $nm }}</option>@endforeach</select></label>
        <label class="text-xs text-gray-500">Show<select name="kind" class="block border rounded px-2 py-1">
            @foreach (['all' => 'Everything', 'actions' => 'Actions only (saved / changed / deleted)', 'pages' => 'Pages viewed', 'logins' => 'Logins & logouts', 'errors' => 'Errors / refused'] as $k => $l)<option value="{{ $k }}" @selected($kind === $k)>{{ $l }}</option>@endforeach
        </select></label>
        <button class="bg-gray-800 text-white rounded px-4 py-1.5">Show</button>
    </form>

    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">{{ $day->format('l j F Y') }} &middot; who was on the system</h2>
        @if ($failed)<p class="text-sm text-red-700 mb-2">&#9888; {{ $failed }} failed login attempt(s) &mdash; choose &ldquo;Logins &amp; logouts&rdquo; to see them.</p>@endif
        @if ($summary->isEmpty())
            <p class="text-sm text-gray-400 italic">No activity recorded for this day.</p>
        @else
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Person</th><th class="py-1">First seen</th><th class="py-1">Last seen</th><th class="py-1 text-right">Pages</th><th class="py-1 text-right">Actions</th><th class="py-1 text-right">Devices</th></tr></thead>
            <tbody>
            @foreach ($summary as $s)
                <tr class="border-b last:border-0">
                    <td class="py-1"><a class="hover:underline font-medium" href="{{ route('manage.activity', ['date' => $day->toDateString(), 'user' => $s->user_id]) }}">{{ $users[$s->user_id] ?? 'User #' . $s->user_id }}</a></td>
                    <td class="py-1">{{ \Carbon\Carbon::parse($s->first_at)->format('H:i') }}</td>
                    <td class="py-1">{{ \Carbon\Carbon::parse($s->last_at)->format('H:i') }}</td>
                    <td class="py-1 text-right">{{ (int) $s->pages }}</td>
                    <td class="py-1 text-right">{{ (int) $s->actions }}</td>
                    <td class="py-1 text-right">{{ (int) $s->devices }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">Activity{{ $userId ? '  ' . ($users[$userId] ?? '') : '' }} <span class="text-xs text-gray-400 font-normal">newest first</span></h2>
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Time</th><th class="py-1">Person</th><th class="py-1">Did</th><th class="py-1">Where</th><th class="py-1">Details</th><th class="py-1">Device</th></tr></thead>
            <tbody>
            @forelse ($rows as $a)
                <tr class="border-b last:border-0 {{ $a->status >= 400 || str_starts_with((string) $a->note, 'LOGIN FAILED') ? 'bg-red-50' : ($a->method !== 'GET' ? 'bg-amber-50' : '') }}">
                    <td class="py-1 whitespace-nowrap">{{ $a->created_at->format('H:i:s') }}</td>
                    <td class="py-1">{{ $a->user->name ?? '' }}</td>
                    <td class="py-1">{{ $a->note ?: ($verb[$a->method] ?? $a->method) }}@if ($a->status >= 400) <span class="text-red-700">(refused {{ $a->status }})</span>@endif</td>
                    <td class="py-1">{{ \App\Http\Controllers\Manage\ActivityController::label($a->route, $a->path) }}<div class="text-xs text-gray-400">{{ $a->path }}</div></td>
                    <td class="py-1 text-xs">{{ $a->subject }}</td>
                    <td class="py-1 text-xs text-gray-500">{{ $device($a->agent) }}<div>{{ $a->ip }}</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-3 text-gray-400 italic">Nothing recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $rows->links() }}</div>
        <p class="text-xs text-gray-400 mt-2">Records each page opened and each save / change / delete, with time, person and device. What was typed (PINs, amounts, codes) is never stored. Kept for 90 days.</p>
    </div>
</div>
@endsection
