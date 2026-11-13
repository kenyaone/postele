<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &mdash; {{ config('pos.cafe_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { display: ['Fraunces', 'Georgia', 'serif'], sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
            colors: { espresso: '#2b1b14', crema: '#f7efe4', roast: '#6b3f26', saffron: '#f5a524' },
            borderRadius: { DEFAULT: '0.6rem', lg: '0.85rem', xl: '1.1rem' },
            boxShadow: { DEFAULT: '0 1px 2px rgba(43,27,20,.06), 0 6px 18px rgba(43,27,20,.07)' }
        } } };
    </script>
    <style>
        body { background-color: #f7efe4; background-image: radial-gradient(rgba(107, 63, 38, .06) 1px, transparent 1px); background-size: 18px 18px; }
        main h2 { font-family: Fraunces, Georgia, serif; letter-spacing: -.01em; }
        main input:not([type=checkbox]):not([type=radio]), main select, main textarea { border-color: rgba(107, 63, 38, .22); background-color: #fff; }
        main input:focus, main select:focus, main textarea:focus { outline: none; border-color: #f5a524; box-shadow: 0 0 0 2px rgba(245, 165, 36, .45); }
        main table thead { color: rgba(107, 63, 38, .8); }
        @media (max-width: 767px) { aside nav::-webkit-scrollbar { display: none; } }
        @media (min-width: 768px) { aside nav { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.35) transparent; } aside nav::-webkit-scrollbar { width: 6px; } aside nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.35); border-radius: 3px; } }
    </style>
@include('partials.pwa')
</head>
<body class="font-sans text-espresso antialiased">
@include('partials.printer-alert')
@php
    // [label, route, icon, active pattern, roles that see it]
    $nav = [
        ['Dashboard',        'manage.index',          '&#128200;', 'manage.index',        ['manager']],
        ['Point of Sale',    'pos.index',             '&#128722;', 'pos.*',               ['manager', 'cashier', 'waiter']],
        ['Bills', 'pos.bills', '&#129534;', 'pos.bills*', ['manager', 'cashier', 'waiter']],
        ['Order history', 'pos.history', '&#128338;', 'pos.history*', ['manager', 'cashier', 'waiter']],
        ['Change my PIN', 'pos.pin', '&#128273;', 'pos.pin*', ['cashier', 'waiter']],
        ['Debts', 'pos.debts', '&#128221;', 'pos.debts*', ['manager', 'cashier', 'waiter']],
        ['M-Pesa payments', 'manage.mpesa', '&#128241;', 'manage.mpesa*', ['manager']],
        ['Cancelled orders', 'manage.voids', '&#10060;', 'manage.voids*', ['manager']],
        ['Printers', 'manage.printers', '&#128424;', 'manage.printers*', ['manager']],
        ['Menu',             'manage.menu',           '&#127869;', 'manage.menu',         ['manager']],
        ['Procurement',      'manage.procurement',    '&#128230;', 'manage.procurement*', ['manager']],
        ['Stock count',      'stock.count',           '&#128203;', 'stock.*',             ['manager', 'stock']],
        ['Yield &amp; variance', 'stock.yield', '&#9878;', 'stock.yield*', ['manager', 'stock']],
        ['Expenses',         'manage.expenses.index', '&#128184;', 'manage.expenses.*',   ['manager']],
        ['HR &amp; Payroll', 'manage.hr',             '&#128101;', 'manage.hr*',          ['manager']],
        ['Reports',          'manage.reports',        '&#128202;', 'manage.reports',     ['manager']],
        ['Waiters',      'manage.reports.staff',  '&#127942;', 'manage.reports.staff',     ['manager']],
        ['Users',            'manage.users.index',    '&#128100;', 'manage.users.*',      ['manager']],
        ['Settings',         'manage.settings',       '&#9881;',   'manage.settings*',    ['manager']],
    ];
@endphp
@php $me = auth()->user(); @endphp
<div class="min-h-screen md:flex">
    <aside class="bg-espresso text-crema md:w-60 md:shrink-0 md:h-screen md:sticky md:top-0 flex flex-col shadow-xl z-20">
        <a href="{{ route('home') }}" class="px-4 pt-4 pb-3 flex items-center gap-2.5">
            @include('partials.brand-mark')
            <span class="leading-tight"><span class="block font-display text-lg font-extrabold">{{ config('pos.cafe_name') }}</span><span class="block text-[11px] uppercase tracking-wider text-crema/50">Back office</span></span>
        </a>
        <nav class="flex flex-wrap md:flex-nowrap md:flex-col md:flex-1 md:min-h-0 gap-1 px-2 pb-3 overflow-x-auto md:overflow-y-auto md:overflow-x-visible">
            @foreach ($nav as [$label, $route, $icon, $active, $roles])
                @continue(! in_array($me->role, $roles, true))
                @if ($route && Route::has($route))
                    @php $on = request()->routeIs($active); @endphp
                    <a href="{{ route($route) }}" class="group flex items-center gap-2.5 px-2.5 py-1.5 rounded-xl whitespace-nowrap text-sm transition {{ $on ? 'bg-saffron text-espresso font-bold shadow' : 'text-crema/80 hover:bg-white/10 hover:text-crema' }}">
                        <span class="w-7 h-7 grid place-items-center rounded-lg {{ $on ? 'bg-espresso/10' : 'bg-white/5 group-hover:bg-white/10' }}">{!! $icon !!}</span><span>{!! $label !!}</span>
                    </a>
                @else
                    <span class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-xl whitespace-nowrap text-sm text-crema/40 cursor-default" title="Coming soon">
                        <span class="w-7 h-7 grid place-items-center">{!! $icon !!}</span><span>{!! $label !!}</span><span class="text-[10px] uppercase bg-white/10 rounded px-1">soon</span>
                    </span>
                @endif
            @endforeach
        </nav>
        <div class="hidden md:flex items-center gap-2.5 mt-auto px-4 py-4 border-t border-white/10 text-sm">
            <span class="w-9 h-9 shrink-0 rounded-full bg-roast grid place-items-center font-bold">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $me->name }}</div><div class="text-[11px] text-crema/50 capitalize">{{ $me->role }}</div></div>
            @if ($me->role === 'manager')<a href="{{ route('manage.settings') }}" title="Settings" class="rounded-lg p-2 text-crema/80 hover:bg-white/10 hover:text-crema text-lg leading-none">&#9881;</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="rounded-lg p-2 text-red-200 hover:bg-red-500/20" title="Log out"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v8"/><path d="M6.3 7.3a8 8 0 1 0 11.4 0"/></svg><span class="sr-only">Log out</span></button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-w-0 p-4 md:p-8">
        <div class="flex flex-wrap items-center justify-between mb-6 gap-4">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">@yield('title')</h1>
            <div class="md:hidden text-sm text-right">
                {{ $me->name }}
                <form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button class="text-red-700 underline ml-2">Log out</button></form>
            </div>
            @yield('actions')
        </div>
        @include('manage.partials.flash')
        @yield('content')
    </main>
</div>
@stack('scripts')
@include('partials.footer')
</body>
</html>
