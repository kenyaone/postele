<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift &mdash; {{ config('pos.cafe_name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
@include('partials.pwa')
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-2xl mx-auto">

        <div class="flex items-center justify-between mb-4">
            <div>
                <a href="{{ route('pos.index') }}" class="text-sm text-gray-500 hover:underline">&larr; POS</a>
                <h1 class="text-2xl font-bold">
                    {{ $shift->isOpen() ? 'X Report (mid-shift)' : 'Z Report (closed)' }}
                </h1>
            </div>
            <div class="text-right text-sm">
                <div>{{ auth()->user()->name }} <span class="text-gray-500">({{ auth()->user()->role }})</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="text-red-600 hover:underline">Logout</button>
                </form>
            </div>
        </div>

        @if (session('ok'))
            <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded p-3 mb-4 text-sm">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">{{ $errors->first() }}</div>
        @endif

        <div class="bg-white rounded-xl shadow p-6 space-y-4">
            <div class="text-sm text-gray-600">
                <div>Cashier: <strong>{{ $shift->user->name }}</strong></div>
                <div>Opened: {{ $shift->opened_at->format('d M Y H:i') }}</div>
                <div>{{ $shift->closed_at ? 'Closed: '.$shift->closed_at->format('d M Y H:i') : 'Shift ongoing' }}</div>
            </div>

            <div class="border-t pt-3 text-sm">
                <div>Tickets: <strong>{{ $report['ticketsPaid'] }}</strong> paid &middot; {{ $report['ticketsVoid'] }} void</div>
            </div>

            <div class="border-t pt-3">
                <h3 class="font-semibold mb-2">By category</h3>
                @if (empty($report['byCategory']))
                    <p class="text-gray-400 italic text-sm">No sales yet.</p>
                @else
                    <table class="w-full text-sm">
                        @foreach ($report['byCategory'] as $cat => $amt)
                            <tr class="border-b last:border-0">
                                <td class="py-1">{{ $cat }}</td>
                                <td class="py-1 text-right font-medium">KSh {{ number_format($amt, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-bold text-lg">
                            <td class="pt-2">TOTAL</td>
                            <td class="pt-2 text-right">KSh {{ number_format($report['totalSales'], 2) }}</td>
                        </tr>
                    </table>
                @endif
            </div>

            <div class="border-t pt-3">
                <h3 class="font-semibold mb-2">By method</h3>
                <table class="w-full text-sm">
                    @foreach (['cash'=>'Cash','kk_stk'=>'M-Pesa STK','kk_till_auto'=>'M-Pesa Till (auto)','kk_till_manual'=>'M-Pesa Till (manual)'] as $k => $label)
                        <tr class="border-b last:border-0">
                            <td class="py-1">{{ $label }}</td>
                            <td class="py-1 text-right font-medium">KSh {{ number_format($report['byMethod'][$k] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

            <div class="border-t pt-3">
                <h3 class="font-semibold mb-2">Cash reconcile</h3>
                <table class="w-full text-sm">
                    <tr><td class="py-1">Opening float</td><td class="py-1 text-right">KSh {{ number_format($shift->cash_opening, 2) }}</td></tr>
                    <tr><td class="py-1">+ Cash sales</td><td class="py-1 text-right">KSh {{ number_format($report['cashSales'], 2) }}</td></tr>
@if (($report['debtCash'] ?? 0) > 0)<tr><td class="py-1">+ Debt repayments (cash)</td><td class="py-1 text-right">KSh {{ number_format($report['debtCash'], 2) }}</td></tr>@endif
                    <tr class="font-semibold border-t">
                        <td class="py-1">= Expected in till</td>
                        <td class="py-1 text-right">KSh {{ number_format($report['cashExpected'], 2) }}</td>
                    </tr>
                    @if ($report['cashDeclared'] !== null)
                        <tr><td class="py-1">Declared</td><td class="py-1 text-right">KSh {{ number_format($report['cashDeclared'], 2) }}</td></tr>
                        <tr class="font-bold {{ abs($report['variance']) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}">
                            <td class="py-1">Variance</td>
                            <td class="py-1 text-right">
                                {{ $report['variance'] >= 0 ? '+' : '-' }}KSh {{ number_format(abs($report['variance']), 2) }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>

            @if ($report['kkUnmatched'] > 0)
                <div class="border-t pt-3">
                    <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded p-2 text-sm">
                        &#9888; {{ $report['kkUnmatched'] }} unmatched Kopo Kopo event(s)
                    </div>
                </div>
            @endif

            {{-- CLOSE SHIFT (only if open) --}}
            @if ($shift->isOpen())
                <div class="border-t pt-4">
                    <h3 class="font-semibold mb-2 text-red-700">Close shift</h3>
                    <form method="POST" action="{{ route('pos.shift.close') }}" class="space-y-2"
                          onsubmit="return confirm('Close shift? Z-report will print, no more sales on this shift.');">
                        @csrf
                        <label class="text-sm font-medium">Count the cash in the till. Declare:</label>
                        <input type="number" name="cash_declared" data-keypad="money" data-keypad-label="Cash counted" step="0.01" min="0" required
                               value="{{ old('cash_declared', number_format($report['cashExpected'], 2, '.', '')) }}"
                               class="w-full border rounded px-3 py-2 text-2xl text-right">
                        <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold rounded py-3">
                            Close shift (Z)
                        </button>
                    </form>
                </div>
            @else
                <div class="border-t pt-4">
                    <a href="{{ route('pos.shift.openForm') }}" class="block w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3 text-center">
                        Open next shift
                    </a>
                </div>
            @endif
        </div>
    </div>
@include('partials.touch')
@include('partials.footer')
</body>
</html>
