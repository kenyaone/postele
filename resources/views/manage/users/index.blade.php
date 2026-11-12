@extends('layouts.manage')
@section('title', 'Users')

@section('content')
<form method="POST" action="{{ route('manage.users.store') }}" class="bg-white rounded-xl shadow p-4 mb-6 grid grid-cols-2 md:grid-cols-5 gap-3 items-end" autocomplete="off">
    @csrf
    <div class="col-span-2"><label class="block text-xs text-gray-500">Name (shown on the sign-in list)</label><input name="name" required maxlength="60" value="{{ old('name') }}" placeholder="e.g. Mercy W." class="w-full border rounded px-2 py-1"></div>
    <div><label class="block text-xs text-gray-500">Role</label>
        <select name="role" class="w-full border rounded px-2 py-1">@foreach ($roles as $k => $l)<option value="{{ $k }}" {{ old('role', 'cashier') === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
    <div><label class="block text-xs text-gray-500">PIN (4-6 digits)</label><input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" required autocomplete="new-password" class="w-full border rounded px-2 py-1"></div>
    <div><label class="block text-xs text-gray-500">Repeat PIN</label><input type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4,6}" required autocomplete="new-password" class="w-full border rounded px-2 py-1"></div>
    <button class="col-span-2 md:col-span-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-2">Add user</button>
</form>

<div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
    <p class="text-xs text-gray-500 mb-2">Cashiers and waiters use the POS. Stock / kitchen staff only see the stock count and shopping list. Managers see everything and switch to a password once they set one.</p>
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b"><tr><th class="py-1">Name</th><th class="py-1">Role</th><th class="py-1">Signs in with</th><th class="py-1">Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr class="border-b last:border-0 {{ $u->active ? '' : 'text-gray-400' }}">
                <td class="py-2 font-medium">{{ $u->name }} @if ($u->id === auth()->id())<span class="text-xs text-gray-400">(you)</span>@endif</td>
                <td class="py-2">{{ $roles[$u->role] ?? $u->role }}</td>
                <td class="py-2">{{ $u->usesPassword() ? 'Password' : 'PIN' }}</td>
                <td class="py-2">{{ $u->active ? 'Active' : 'Deactivated' }}</td>
                <td class="py-2 text-right"><a href="{{ route('manage.users.edit', $u) }}" class="text-emerald-700 hover:underline">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
