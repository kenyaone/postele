<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS &mdash; Tele Caf&eacute;</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">POS</h1>
            <div class="text-right text-sm">
                <div>{{ auth()->user()->name }} <span class="text-gray-500">({{ auth()->user()->role }})</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="text-red-600 hover:underline">Logout</button>
                </form>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-600">Cashier screen coming in Task #6 (order ticket flow).</p>
        </div>
    </div>
</body>
</html>
