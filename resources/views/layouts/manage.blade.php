<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &mdash; {{ config('pos.cafe_name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
@php
    // [label, route, icon, active pattern, roles that see it]
    $nav = [
        ['Dashboard',        'manage.index',          '&#128200;', 'manage.index',        ['manager']],
        ['Point of Sale',    'pos.index',             '&#128722;', 'pos.*',               ['manager', 'cashier', 'waiter']],
        ['Bills', 'pos.bills', '&#129534;', 'pos.bills*', ['manager', 'cashier', 'waiter']],
        ['Menu',             'manage.menu',           '&#127869;', 'manage.menu',         ['manager']],
        ['Procurement',      'manage.procurement',    '&#128230;', 'manage.procurement*', ['manager']],
        ['Stock count',      'stock.count',           '&#128203;', 'stock.*',             ['manager', 'stock']],
        ['Yield &amp; variance', 'stock.yield', '&#9878;', 'stock.yield*', ['manager', 'stock']],
        ['Expenses',         'manage.expenses.index', '&#128184;', 'manage.expenses.*',   ['manager']],
        ['HR &amp; Payroll', 'manage.hr',             '&#128101;', 'manage.hr*',          ['manager']],
        ['Reports',          'manage.reports',        '&#128202;', 'manage.reports*',     ['manager']],
        ['Users',            'manage.users.index',    '&#128100;', 'manage.users.*',      ['manager']],
        ['Settings',         'manage.settings',       '&#9881;',   'manage.settings*',    ['manager']],
    ];
@endphp
<div class="min-h-screen md:flex">
    <aside class="bg-emerald-900 text-emerald-50 md:w-56 md:shrink-0 md:h-screen md:sticky md:top-0 flex flex-col">
        <div class="px-4 pt-4 pb-3">
            <div class="font-bold text-lg leading-tight">{{ config('pos.cafe_name') }}</div>
            <div class="text-xs text-emerald-300">Back office</div>
        </div>
        <nav class="flex md:flex-col gap-1 px-2 pb-3 overflow-x-auto md:overflow-visible">
            @foreach ($nav as [$label, $route, $icon, $active, $roles])
                @continue(! in_array(auth()->user()->role, $roles, true))
                @if ($route && Route::has($route))
                    <a href="{{ route($route) }}"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg whitespace-nowrap text-sm {{ request()->routeIs($active) ? 'bg-emerald-700 text-white font-semibold' : 'hover:bg-emerald-800' }}">
                        <span>{!! $icon !!}</span> <span>{!! $label !!}</span>
                    </a>
                @else
                    <span class="flex items-center gap-2 px-3 py-2 rounded-lg whitespace-nowrap text-sm text-emerald-400 cursor-default" title="Coming soon">
                        <span>{!! $icon !!}</span> <span>{!! $label !!}</span>
                        <span class="text-[10px] uppercase bg-emerald-800 rounded px-1">soon</span>
                    </span>
                @endif
            @endforeach
        </nav>
        <div class="hidden md:block mt-auto px-4 py-4 text-sm border-t border-emerald-800">
            <div>{{ auth()->user()->name }}</div>
            <div class="text-emerald-300 text-xs mb-2">{{ auth()->user()->role }}</div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-emerald-200 hover:text-white underline">Log out</button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex items-center justify-between mb-6 gap-4">
            <h1 class="text-2xl font-bold">@yield('title')</h1>
            <div class="md:hidden text-sm text-right">
                {{ auth()->user()->name }}
                <form method="POST" action="{{ route('logout') }}" class="inline">@csrf
                    <button class="text-red-600 underline ml-2">Log out</button>
                </form>
            </div>
            @yield('actions')
        </div>
        @include('manage.partials.flash')
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
