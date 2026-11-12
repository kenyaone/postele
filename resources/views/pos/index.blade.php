<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS &mdash; {{ config('pos.cafe_name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        {{-- Top bar --}}
        <header class="bg-white shadow px-4 py-2 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="font-bold text-lg">POS</h1>
                @if($ticket)
                    <span class="text-sm bg-gray-100 rounded px-2 py-1">
                        Ticket #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}
                        &mdash; <span class="font-semibold uppercase">{{ $ticket->status }}</span>
                    </span>
                @endif
                @isset($shift)
                    @if ($shift)
                        <a href="{{ route('pos.shift.show') }}"
                           class="text-sm bg-emerald-100 text-emerald-800 rounded px-2 py-1 hover:bg-emerald-200">
                            Shift open &middot; {{ $shift->opened_at->format('H:i') }}
                        </a>
                    @endif
                @endisset
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span>{{ auth()->user()->name }} <span class="text-gray-500">({{ auth()->user()->role }})</span></span>
                @if(auth()->user()->role === 'manager')
                    <a href="{{ route('manage.index') }}" class="text-blue-600 hover:underline">Manage</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="text-red-600 hover:underline">Logout</button>
                </form>
            </div>
        </header>

        {{-- Flash --}}
        @if (session('ok') || session('change') !== null || $errors->any())
            <div class="px-4 pt-3">
                @if (session('ok'))
                    <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded p-2 text-sm">{{ session('ok') }}</div>
                @endif
                @if (session('change') !== null)
                    <div class="bg-emerald-100 border border-emerald-300 text-emerald-900 rounded p-3 text-lg font-bold">
                        PAID &#10003; &nbsp;&mdash;&nbsp; Change: KSh {{ number_format(session('change'), 2) }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="bg-red-100 border border-red-300 text-red-800 rounded p-2 text-sm">{{ $errors->first() }}</div>
                @endif
            </div>
        @endif

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 p-4">
            {{-- MENU --}}
            <div class="lg:col-span-2 space-y-5">
                @if (! $ticket)
                    <div class="bg-white rounded-xl shadow p-6 text-center">
                        <p class="text-gray-600 mb-4">No open ticket.</p>
                        <form method="POST" action="{{ route('pos.tickets.store') }}">@csrf
                            <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-lg rounded-xl px-6 py-3">
                                + Start new ticket
                            </button>
                        </form>
                    </div>
                @endif

                @foreach ($categories as $cat)
                    @if ($cat->items->isNotEmpty())
                        <div>
                            <h2 class="font-semibold text-emerald-800 mb-2">{{ $cat->name }}</h2>
                            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2">
                                @foreach ($cat->items as $item)
                                    <form method="POST" action="{{ $ticket ? route('pos.tickets.items.store', $ticket) : '#' }}">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                                        <button type="submit"
                                                {{ ($ticket && $ticket->status === 'open') ? '' : 'disabled' }}
                                                class="w-full h-full bg-white rounded-lg shadow hover:shadow-md hover:bg-emerald-50 disabled:opacity-40 disabled:cursor-not-allowed p-3 text-left">
                                            <div class="font-medium text-sm">{{ $item->name }}</div>
                                            <div class="text-emerald-700 font-bold">KSh {{ number_format($item->price, 2) }}</div>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- TICKET PANEL --}}
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow p-4 sticky top-4">
                    @if (! $ticket)
                        <p class="text-gray-500 text-sm">Start a ticket to begin.</p>
                    @else
                        <h2 class="font-bold mb-3">Ticket #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</h2>

                        @if ($ticket->items->isEmpty())
                            <p class="text-sm text-gray-500 italic mb-3">Tap items on the left &rarr;</p>
                        @else
                            <table class="w-full text-sm mb-3">
                                <tbody>
                                @foreach ($ticket->items as $line)
                                    <tr class="border-b last:border-0">
                                        <td class="py-1">
                                            <div class="font-medium">{{ $line->item->name }}</div>
                                            <div class="text-xs text-gray-500">KSh {{ number_format($line->unit_price, 2) }} ea</div>
                                        </td>
                                        <td class="py-1 w-16 text-center">
                                            @if ($ticket->status === 'open')
                                                <form method="POST" action="{{ route('pos.tickets.items.update', [$ticket, $line]) }}" class="inline">
                                                    @csrf @method('PATCH')
                                                    <input type="number" name="qty" value="{{ $line->qty }}" min="0" max="999"
                                                           class="w-12 border rounded text-center"
                                                           onchange="this.form.submit()">
                                                </form>
                                            @else
                                                {{ $line->qty }}
                                            @endif
                                        </td>
                                        <td class="py-1 w-20 text-right font-medium">
                                            KSh {{ number_format($line->subtotal, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                            <div class="flex justify-between font-bold text-lg border-t pt-2 mb-3">
                                <span>TOTAL</span>
                                <span>KSh {{ number_format($ticket->total, 2) }}</span>
                            </div>
                        @endif

                        {{-- STATE-DRIVEN ACTIONS --}}
                        @if ($ticket->status === 'open')
                            <form method="POST" action="{{ route('pos.tickets.send', $ticket) }}" class="mb-2">
                                @csrf
                                <button {{ $ticket->items->isEmpty() ? 'disabled' : '' }}
                                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3 disabled:opacity-40 disabled:cursor-not-allowed">
                                    Send to kitchen
                                </button>
                            </form>
                            <form method="POST" action="{{ route('pos.tickets.void', $ticket) }}"
                                  onsubmit="return confirm('Void this ticket?');">
                                @csrf
                                <button class="w-full text-red-600 text-sm hover:underline">Void ticket</button>
                            </form>

                        @elseif ($ticket->status === 'sent')
                            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 text-sm rounded p-2 mb-3">
                                &#10003; Sent to kitchen &mdash; KOT queued
                            </div>
                            <form method="POST" action="{{ route('pos.tickets.close', $ticket) }}" class="space-y-2">
                                @csrf
                                <label class="text-sm font-medium">Cash received (KSh)</label>
                                <input type="number" name="cash_received" step="0.01" min="0" required autofocus
                                       class="w-full border rounded px-3 py-2 text-xl text-right"
                                       value="{{ old('cash_received', (string) ceil($ticket->total)) }}">
                                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">
                                    Close bill (Cash)
                                </button>
                            </form>
                            <form method="POST" action="{{ route('pos.tickets.void', $ticket) }}"
                                  onsubmit="return confirm('Void this ticket?');" class="mt-2">
                                @csrf
                                <button class="w-full text-red-600 text-sm hover:underline">Void ticket</button>
                            </form>

                        @elseif ($ticket->status === 'paid')
                            <div class="bg-emerald-600 text-white rounded p-4 text-center mb-3">
                                <div class="text-2xl font-bold">&#10003; PAID</div>
                                <div class="text-sm opacity-90">Receipt queued</div>
                            </div>
                            <form method="POST" action="{{ route('pos.tickets.store') }}">
                                @csrf
                                <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded py-3">
                                    + Start new ticket
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
