<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage &mdash; Tele Caf&eacute;</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Manage</h1>
            <div class="text-right text-sm">
                <div>{{ auth()->user()->name }} <span class="text-gray-500">({{ auth()->user()->role }})</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="text-red-600 hover:underline">Logout</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ route('manage.menu') }}" class="block bg-white rounded-xl shadow p-6 hover:shadow-lg transition">
                <div class="text-lg font-semibold">Menu</div>
                <div class="text-gray-500 text-sm">Categories, items, prices, stations</div>
            </a>
            <a href="{{ route('manage.settings') }}" class="block bg-white rounded-xl shadow p-6 hover:shadow-lg transition">
                <div class="text-lg font-semibold">Settings</div>
                <div class="text-gray-500 text-sm">Till number, caf&eacute; name, phone, WhatsApp</div>
            </a>
            <div class="bg-white rounded-xl shadow p-6 opacity-50">
                <div class="text-lg font-semibold">Reports</div>
                <div class="text-gray-500 text-sm">Coming in Task #9 (X/Z, shift close)</div>
            </div>
        </div>
    </div>
</body>
</html>
