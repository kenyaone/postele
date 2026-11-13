<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('pos.cafe_name') }} POS &mdash; Sign in</title>
    <script src="https://cdn.tailwindcss.com"></script>
@include('partials.pwa')
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl shadow p-8">
        @if (config('pos.logo'))<img src="{{ config('pos.logo') }}" alt="" class="w-20 h-20 mx-auto mb-3 rounded-3xl object-cover shadow">@endif
        <h1 class="text-2xl font-bold text-center">{{ config('pos.cafe_name') }} POS</h1>@if (config('pos.address'))<p class="text-center text-sm opacity-70">{{ config('pos.address') }}</p>@endif
        <p class="text-center text-gray-500 mb-6">Sign in</p>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">Who are you?</label>
            <select name="user_id" id="user_id" required
                    class="w-full border rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" data-auth="{{ $user->usesPassword() ? 'password' : 'pin' }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }} ({{ $user->role }})
                    </option>
                @endforeach
            </select>

            <label id="secret-label" class="block text-sm font-medium text-gray-700 mb-1">PIN</label>
            <input type="password" name="secret" id="secret" data-keypad="pin" data-keypad-label="PIN" data-keypad-text="1" autocomplete="off" autofocus required
                   class="w-full border rounded-lg px-3 py-2 mb-6 text-center text-2xl tracking-widest focus:outline-none focus:ring-2 focus:ring-emerald-500">

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg py-3">Sign in</button>
        </form>
    </div>
    <script>
        (function () {
            var sel = document.getElementById('user_id'), inp = document.getElementById('secret'), lbl = document.getElementById('secret-label');
            function sync() {
                var pw = sel.options[sel.selectedIndex].dataset.auth === 'password';
                lbl.textContent = pw ? 'Password' : 'PIN';
                inp.inputMode = pw ? 'text' : 'numeric';
                inp.autocomplete = pw ? 'current-password' : 'off';
                inp.classList.toggle('tracking-widest', !pw);
                inp.classList.toggle('text-2xl', !pw);
            }
            sel.addEventListener('change', function () { sync(); inp.value = ''; inp.focus(); });
            sync();
        })();
    </script>
@include('partials.touch')
@include('partials.footer')
</body>
</html>
