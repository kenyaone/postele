@php
    $kkReady   = \App\Services\KopoKopo::ready();
    $stkLatest = \App\Models\StkRequest::where('ticket_id', $ticket->id)->latest('id')->first();
    $stkWait   = $stkLatest && $stkLatest->status === 'pending' && $stkLatest->created_at->gt(now()->subMinutes(3));
    $mpesaEvents = \App\Models\KkEvent::where('event_type', 'buygoods_transaction_received')->where('status', 'pending')
        ->where('received_at', '>=', now()->subHours(3))->orderByDesc('received_at')->limit(8)->get();
    $billKsh = (int) ceil($ticket->total);
@endphp
<div class="border-t mt-3 pt-3 space-y-3">
    <h3 class="font-semibold text-sm text-green-800">M-Pesa &middot; KSh {{ number_format($billKsh) }}</h3>

    @if ($stkWait)
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded p-2 text-sm">Waiting for the customer to enter their M-Pesa PIN&hellip;</div>
        <script>
            (function () {
                var url = @json(route('pos.tickets.mpesa.status', $ticket)), n = 0;
                var t = setInterval(function () {
                    n++;
                    fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
                        if (d.ticket === 'paid' || d.stk !== 'pending' || n > 60) { clearInterval(t); location.reload(); }
                    }).catch(function () {});
                }, 3000);
            })();
        </script>
    @elseif ($stkLatest && $stkLatest->status === 'failed' && $stkLatest->created_at->gt(now()->subMinutes(10)))
        <div class="bg-red-50 border border-red-300 text-red-800 rounded p-2 text-sm">Last prompt did not go through: {{ $stkLatest->error }}</div>
    @endif

    <form method="POST" action="{{ route('pos.tickets.mpesa.stk', $ticket) }}" class="flex gap-2">
        @csrf
        <input name="phone" inputmode="tel" required placeholder="Customer phone 07..." class="flex-1 min-w-0 border rounded px-2 py-1" {{ $kkReady ? '' : 'disabled' }}>
        <button class="bg-green-700 hover:bg-green-800 text-white text-sm rounded px-3 py-1 disabled:opacity-40" {{ $kkReady ? '' : 'disabled' }}>Send prompt</button>
    </form>
    @unless ($kkReady)<p class="text-xs text-gray-500 -mt-2">Phone prompts switch on once Kopo Kopo approves the app.</p>@endunless

    @if ($mpesaEvents->isNotEmpty())
        <div>
            <div class="text-xs text-gray-500 mb-1">Paid to the till? Tap the matching payment:</div>
            @foreach ($mpesaEvents as $ev)
                @php $r = $ev->raw_payload['event']['resource'] ?? []; $who = trim(($r['sender_first_name'] ?? '') . ' ' . ($r['sender_last_name'] ?? '')); $enough = (float) $ev->amount + 0.004 >= $ticket->total; @endphp
                <form method="POST" action="{{ route('pos.tickets.mpesa.match', $ticket) }}" onsubmit="return confirm('Use M-Pesa {{ $ev->reference }} for this bill?');">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $ev->id }}">
                    <button {{ $enough ? '' : 'disabled' }} class="w-full text-left border rounded px-2 py-1 mb-1 text-sm hover:bg-green-50 disabled:opacity-40">
                        <b>KSh {{ number_format((float) $ev->amount) }}</b> &middot; {{ $ev->reference }} &middot; {{ $who ?: 'customer' }} &middot; {{ $ev->received_at->format('H:i') }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <details class="text-sm" {{ $errors->has('mpesa') || $errors->has('code') ? 'open' : '' }}>
        <summary class="cursor-pointer text-gray-600">Enter an M-Pesa code by hand</summary>
        <form method="POST" action="{{ route('pos.tickets.mpesa.manual', $ticket) }}" class="grid grid-cols-2 gap-2 mt-2">
            @csrf
            <input name="code" required maxlength="12" value="{{ old('code') }}" placeholder="e.g. QK12ABC3XY" class="border rounded px-2 py-1 uppercase">
            <input type="number" name="amount" step="1" min="1" required value="{{ old('amount', $billKsh) }}" class="border rounded px-2 py-1 text-right">
            <button class="col-span-2 border border-green-700 text-green-800 rounded py-1 hover:bg-green-50">Record M-Pesa payment</button>
        </form>
    </details>
</div>
