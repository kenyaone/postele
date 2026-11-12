@php $pr = app(\App\Services\PrintRouter::class); $rKot = $pr->hasNetwork('kot'); $rBill = $pr->hasNetwork('bill'); @endphp
@if ($rKot || $rBill)
    <span id="print-chip" data-url="{{ route('pos.tickets.printstatus', $ticket) }}" class="font-semibold">&#128424; Checking printer&hellip;</span>
    <span class="flex gap-2">
        <button type="button" onclick="{{ $rKot ? "reprintRemote('kot')" : "printBill('" . route('pos.tickets.bill', [$ticket, 'mode' => 'kot']) . "')" }}" class="font-semibold underline-offset-2 hover:underline">Reprint KOT</button>
        <button type="button" onclick="{{ $rBill ? "reprintRemote('bill')" : "printBill('" . route('pos.tickets.bill', $ticket) . "')" }}" class="font-semibold underline-offset-2 hover:underline">&#129534; Reprint bill</button>
    </span>
    <script>
        (function () {
            var c = document.getElementById('print-chip'); if (!c) return; var n = 0;
            function draw(j) {
                if (!j.length) { c.textContent = 'Not printed yet'; return false; }
                var parts = [];
                [['kot', 'KOT'], ['bill', 'Bill'], ['receipt', 'Receipt']].forEach(function (k) {
                    var x = j.find(function (y) { return y.kind === k[0]; }); if (!x) return;
                    parts.push(k[1] + (x.status === 'sent' ? ' printed  ' + x.at : x.status === 'failed' ? ' NOT printed ' : ' printing'));
                });
                var fail = j.some(function (y) { return y.status === 'failed'; }), wait = j.some(function (y) { return y.status === 'pending'; });
                c.textContent = ' ' + parts.join('  ');
                c.className = 'font-semibold ' + (fail ? 'text-red-700' : wait ? '' : 'text-emerald-700');
                return wait;
            }
            function poll() {
                fetch(c.dataset.url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
                    .then(function (d) { if (draw(d.jobs) && ++n < 40) setTimeout(poll, 3000); })
                    .catch(function () { if (++n < 40) setTimeout(poll, 5000); });
            }
            window.reprintRemote = function (what) {
                var f = new FormData(); f.append('what', what); f.append('_token', '{{ csrf_token() }}');
                c.textContent = 'Sending to the cafe printer'; c.className = 'font-semibold';
                fetch('{{ route('pos.tickets.reprint', $ticket) }}', { method: 'POST', body: f, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
                    .then(function () { n = 0; setTimeout(poll, 1500); });
            };
            poll();
        })();
    </script>
@else
    <button type="button" onclick="printBill('{{ route('pos.tickets.bill', $ticket) }}')" class="font-semibold hover:underline">&#129534; Reprint bill</button>
@endif
