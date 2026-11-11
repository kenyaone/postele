<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tele Caf&eacute; POS &mdash; Sign in</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        <h1 class="text-2xl font-bold text-center">Tele Caf&eacute; POS</h1>
        <p class="text-center text-gray-500 mb-6">Sign in</p>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">Who are you?</label>
            <select name="user_id" required
                    class="w-full border rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }} ({{ $user->role }})
                    </option>
                @endforeach
            </select>

            <label class="block text-sm font-medium text-gray-700 mb-1">PIN</label>
            <input type="password" name="pin" inputmode="numeric" autocomplete="off" autofocus required
                   class="w-full border rounded-lg px-3 py-2 mb-6 text-center text-2xl tracking-widest focus:outline-none focus:ring-2 focus:ring-emerald-500">

            <button type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg py-3">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
