@extends('layouts.manage')
@section('title', 'Edit ' . $user->name)

@section('content')
<a href="{{ route('manage.users.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Users</a>
<form method="POST" action="{{ route('manage.users.update', $user) }}" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4 mt-3" autocomplete="off">
    @csrf @method('PATCH')
    <div><label class="block text-sm text-gray-600">Name</label><input name="name" required maxlength="60" value="{{ old('name', $user->name) }}" class="w-full border rounded px-3 py-2"></div>
    <div><label class="block text-sm text-gray-600">Role</label>
        <select name="role" class="w-full border rounded px-3 py-2" {{ $isSelf ? 'disabled' : '' }}>@foreach ($roles as $k => $l)<option value="{{ $k }}" {{ old('role', $user->role) === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select>
        @if ($isSelf)<input type="hidden" name="role" value="{{ $user->role }}"><p class="text-xs text-gray-500 mt-1">You cannot change your own role.</p>@endif
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="active" value="{{ $isSelf ? 1 : 0 }}">
        <input type="checkbox" name="active" value="1" {{ old('active', $user->active) ? 'checked' : '' }} {{ $isSelf ? 'disabled' : '' }}>
        Active (can sign in)
    </label>
    <div class="border-t pt-4">
        <div class="text-sm font-medium mb-1">{{ $user->usesPassword() ? 'Reset to a PIN' : 'Change PIN' }} <span class="text-gray-400 font-normal">(leave blank to keep the current one)</span></div>
        <div class="grid grid-cols-2 gap-3">
            <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" placeholder="New PIN" autocomplete="new-password" class="border rounded px-3 py-2">
            <input type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4,6}" placeholder="Repeat PIN" autocomplete="new-password" class="border rounded px-3 py-2">
        </div>
        @if ($user->usesPassword())
            <label class="flex items-center gap-2 text-sm mt-2"><input type="checkbox" name="reset_to_pin" value="1"> This manager forgot their password: switch them back to this PIN (they can set a new password after signing in)</label>
        @endif
    </div>
    <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">Save</button>
</form>
@endsection
