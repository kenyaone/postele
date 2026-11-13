@php
    $kkReady    = \App\Services\KopoKopo::ready();
    $stkLatest  = \App\Models\StkRequest::where('ticket_id', $ticket->id)->latest('id')->first();
    $stkWait    = $stkLatest && $stkLatest->status === 'pending' && $stkLatest->created_at->gt(now()->subMinutes(3));
    $mpesaEvents = \App\Models\KkEvent::where('event_type', 'buygoods_transaction_received')->where('status', 'pending')
        ->where('received_at', '>=', now()->subHours(3))->orderByDesc('received_at')->limit(8)->get();
    $billKsh = (int) ceil($ticket->total);
    $otherBills = \App\Models\Ticket::with('items', 'opener')->whereIn('status', ['open', 'sent'])->where('id', '!=', $ticket->id)
        ->where('created_at', '>=', now()->subHours(12))
        ->when(auth()->user()->role !== 'manager', fn ($q) => $q->where('opened_by', auth()->id()))
        ->orderByDesc('id')->limit(15)->get()->filter(fn ($t) => $t->total > 0);
@endphp
<div class="space-y-3">
    @if ($stkWait)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 text-center">
            <div class="text-3xl animate-bounce">&#128241;</div>
            <div class="font-bold text-amber-900">Waiting for the customer's M-Pesa PIN&hellip;</div>
            <div class="text-xs text-amber-800 mt-0.5">This screen updates by itself when they pay.</div>
        </div>
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
        <div class="rounded-2xl bg-red-50 ring-1 ring-red-200 text-red-800 text-sm p-3">&#9888; The last prompt did not go through: {{ $stkLatest->error }}</div>
    @endif

    @if ($otherBills->isNotEmpty())
        <details class="rounded-2xl bg-white ring-1 ring-roast/10 p-3 text-sm" id="multiBills">
            <summary class="cursor-pointer font-semibold text-roast/80">&#128101; Customer paying for several orders? Tick them</summary>
            <div class="mt-2 space-y-1">
                @foreach ($otherBills as $ob)
                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-green-50">
                        <input type="checkbox" class="also-bill w-5 h-5 accent-green-600" value="{{ $ob->id }}" data-total="{{ (float) $ob->total }}">
                        <span class="flex-1 min-w-0 truncate"><b>{{ $ob->order_no }}</b> <span class="text-xs text-roast/60">{{ $ob->opener?->name }} &middot; {{ $ob->items->whereNull('void_at')->map(fn ($i) => $i->qty . ' ' . ($i->item->name ?? ''))->take(3)->implode(', ') }}</span></span>
                        <span class="font-semibold shrink-0">KSh {{ number_format($ob->total) }}</span>
                    </label>
                @endforeach
            </div>
            <div class="mt-2 rounded-lg bg-green-50 px-3 py-2 font-bold text-green-900">Together: KSh <span id="multiTotal">{{ number_format($billKsh) }}</span></div>
            <p class="text-xs text-roast/60 mt-1">Then send the prompt, tap their till payment, or type their M-Pesa code. All ticked orders are paid with one M-Pesa.</p>
        </details>
    @endif

    <div class="rounded-2xl bg-white ring-1 ring-roast/10 p-3">
        <div class="text-xs font-bold uppercase tracking-wider text-roast/60 mb-2"><span class="inline-grid place-items-center w-5 h-5 rounded-full bg-green-600 text-white mr-1">1</span> Send a prompt to their phone</div>
        <form method="POST" action="{{ route('pos.tickets.mpesa.stk', $ticket) }}" class="mp-pay flex flex-wrap gap-2">
            @csrf
            <input name="orders" value="{{ old('orders') }}" placeholder="Other orders too? e.g. 14, 15" {{ $kkReady ? '' : 'disabled' }}
                   class="w-full rounded-xl ring-1 ring-roast/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
            <input name="phone" inputmode="tel" data-keypad="phone" data-keypad-label="Customer phone" maxlength="13" required placeholder="07xx xxx xxx" value="{{ old('phone') }}" {{ $kkReady ? '' : 'disabled' }}
                   class="flex-1 min-w-0 rounded-xl ring-1 ring-roast/20 px-3 py-3 text-lg tracking-wide focus:outline-none focus:ring-2 focus:ring-green-600">
            <button {{ $kkReady ? '' : 'disabled' }} class="shrink-0 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold px-4 disabled:opacity-40">Send<span class="block text-xs font-medium opacity-90">KSh <span id="stkAmt">{{ number_format($billKsh) }}</span></span></button>
        </form>
        @unless ($kkReady)<p class="text-xs text-roast/60 mt-1">Phone prompts switch on once Kopo Kopo approves the app.</p>@else<p class="text-xs text-roast/60 mt-1">Paying for other orders too? Type their order numbers (today's short number like 14 is enough), or tick them above. One prompt asks for the total.</p>@endunless
    </div>


    <div class="rounded-2xl bg-white ring-1 ring-roast/10 p-3">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-bold uppercase tracking-wider text-roast/60"><span class="inline-grid place-items-center w-5 h-5 rounded-full bg-green-600 text-white mr-1">2</span> Paid Till {{ config('pos.till_number') }}? Tap it</div>
            <a href="{{ url()->current() }}" class="text-xs font-semibold text-green-700 hover:underline">&#8635; Refresh</a>
        </div>
        @if ($mpesaEvents->isEmpty())
            <p class="text-sm text-roast/60 py-2 text-center">No new till payments in the last 3 hours.</p>
        @else
            @foreach ($mpesaEvents as $ev)
                @php
                    $r = $ev->raw_payload['event']['resource'] ?? [];
                    $who = trim(($r['sender_first_name'] ?? '') . ' ' . ($r['sender_last_name'] ?? ''));
                    $enough = (float) $ev->amount + 0.004 >= $ticket->total;
                    $exact = abs((float) $ev->amount - $ticket->total) < 0.005;
                @endphp
                <form method="POST" action="{{ route('pos.tickets.mpesa.match', $ticket) }}" class="mp-pay" data-ref="{{ $ev->reference }}">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $ev->id }}">
                    <button {{ $enough ? '' : 'disabled' }} data-amount="{{ (float) $ev->amount }}"
                            class="w-full flex items-center justify-between gap-3 text-left rounded-xl px-3 py-2.5 mb-1.5 ring-1 {{ $exact ? 'ring-2 ring-green-500 bg-green-50' : 'ring-roast/15 bg-white' }} hover:bg-green-50 disabled:opacity-40 disabled:cursor-not-allowed">
                        <span class="min-w-0">
                            <span class="block font-semibold truncate">{{ $who !== '' ? $who : 'Customer' }}</span>
                            <span class="block text-xs text-roast/60 font-mono">{{ $ev->reference }} &middot; {{ $ev->received_at->format('H:i') }}</span>
                        </span>
                        <span class="text-right shrink-0">
                            <span class="block font-extrabold">KSh {{ number_format((float) $ev->amount) }}</span>
                            <span class="mp-tag text-[11px] font-bold {{ $exact ? 'text-green-700' : 'text-red-600' }}">{!! $exact ? '&#10003; EXACT' : (! $enough ? 'too little' : '') !!}</span>
                        </span>
                    </button>
                </form>
            @endforeach
        @endif
    </div>

    <details class="rounded-2xl bg-white ring-1 ring-roast/10 p-3 text-sm" {{ $errors->has('mpesa') || $errors->has('code') ? 'open' : '' }}>
        <summary class="cursor-pointer font-semibold text-roast/80"><span class="inline-grid place-items-center w-5 h-5 rounded-full bg-roast/60 text-white text-xs mr-1">3</span> Not showing? Type the M-Pesa code</summary>
        <form method="POST" action="{{ route('pos.tickets.mpesa.manual', $ticket) }}" class="mp-pay grid grid-cols-1 gap-2 mt-3">
            @csrf
            <input name="code" required maxlength="12" value="{{ old('code') }}" placeholder="e.g. QK12ABC3XY" class="rounded-xl ring-1 ring-roast/20 px-3 py-2.5 uppercase font-mono">
            <button class="rounded-xl ring-2 ring-green-700 text-green-800 font-semibold py-2.5 hover:bg-green-50">Check with Kopo Kopo</button>
            <p class="text-xs text-roast/60">The bill is only marked paid once Kopo Kopo has received this payment on Till {{ config('pos.till_number') }}.</p>
        </form>
    </details>
</div>
<script>
(function () {
    var base = {{ (float) $ticket->total }};
    function picked() { return Array.prototype.slice.call(document.querySelectorAll('.also-bill:checked')); }
    function total() { return picked().reduce(function (s, b) { return s + parseFloat(b.dataset.total || 0); }, base); }
    function refresh() {
        var t = total(), el = document.getElementById('multiTotal');
        if (el) el.textContent = Math.round(t).toLocaleString();
        var sa = document.getElementById('stkAmt'); if (sa) sa.textContent = Math.round(t).toLocaleString();
        document.querySelectorAll('.mp-pay button[data-amount]').forEach(function (btn) {
            var a = parseFloat(btn.dataset.amount), exact = Math.abs(a - t) < 0.01, enough = a + 0.004 >= t;
            btn.disabled = ! enough;
            btn.classList.toggle('ring-2', exact); btn.classList.toggle('ring-green-500', exact); btn.classList.toggle('bg-green-50', exact);
            var tag = btn.querySelector('.mp-tag');
            if (tag) { tag.innerHTML = exact ? '&#10003; EXACT' : (enough ? '' : 'too little'); tag.className = 'mp-tag text-[11px] font-bold ' + (exact ? 'text-green-700' : 'text-red-600'); }
        });
    }
    document.querySelectorAll('.also-bill').forEach(function (b) { b.addEventListener('change', refresh); });
    document.querySelectorAll('form.mp-pay').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var n = picked().length;
            if (f.dataset.ref && ! confirm('Use M-Pesa ' + f.dataset.ref + ' for ' + (n ? (n + 1) + ' bills (KSh ' + Math.round(total()).toLocaleString() + ')' : 'this bill') + '?')) { e.preventDefault(); return; }
            f.querySelectorAll('input[name="also[]"]').forEach(function (x) { x.remove(); });
            picked().forEach(function (b) { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'also[]'; h.value = b.value; f.appendChild(h); });
        });
    });
})();
</script>
