@extends('layouts.manage')
@section('title', 'Printers')

@php
    use App\Models\Printer;
    $tiles = [
        ['Bill / receipt printer', $bill->isNotEmpty() ? 'ok' : 'todo', $bill->isNotEmpty() ? $bill->pluck('name')->implode(', ') : 'Add a printer with role Bills or Both.'],
        ['Kitchen (KOT) printer', $kot->isNotEmpty() ? 'ok' : 'todo', $kot->isNotEmpty() ? $kot->pluck('name')->implode(', ') : 'Add a printer with role Kitchen or Both.'],
        ['KOT station routing', $kot->isEmpty() ? 'todo' : ($uncovered->isEmpty() ? 'ok' : 'warn'), $kot->isEmpty() ? 'Add KOT printers first.' : ($uncovered->isEmpty() ? 'Every dish has a kitchen printer.' : 'No printer for: ' . $uncovered->map(fn ($s) => Printer::STATIONS[$s] ?? $s)->implode(', '))],
        ['Print bridge for WiFi', $network->isEmpty() ? 'idle' : ($bridgeOk ? 'ok' : 'warn'), $network->isEmpty() ? 'Only needed for network printers.' : ($bridgeOk ? 'Running. Last check ' . $seenAt->diffForHumans() . '.' : ($seenAt ? 'Stopped. Last seen ' . $seenAt->diffForHumans() . '.' : 'Not started yet. See step below.'))],
    ];
    $tone = ['ok' => 'bg-emerald-50 ring-emerald-200 text-emerald-900', 'todo' => 'bg-amber-50 ring-amber-200 text-amber-900', 'warn' => 'bg-red-50 ring-red-200 text-red-900', 'idle' => 'bg-gray-50 ring-gray-200 text-gray-700'];
    $icon = ['ok' => '&#10003;', 'todo' => '!', 'warn' => '!', 'idle' => '&#8211;'];
    $dot = ['ok' => 'bg-emerald-600 text-white', 'todo' => 'bg-amber-400 text-white', 'warn' => 'bg-red-600 text-white', 'idle' => 'bg-gray-300 text-gray-700'];
@endphp

@section('content')
<div class="max-w-5xl space-y-5">
    <div class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
            <h2 class="font-display text-2xl font-extrabold flex items-center gap-2"><span class="text-orange-500">&#128424;</span> Thermal printers</h2>
            <p class="text-sm text-gray-600 mt-1">Print bills, kitchen tickets (KOTs) and receipts straight from any phone or tablet, with no app on staff devices. Without a printer here, printing works through the device print dialog as before.</p>
        </div>
        <button type="button" onclick="openPrinter()" class="shrink-0 rounded-full bg-orange-500 hover:bg-orange-600 text-white font-bold px-5 py-3 shadow">+ Add printer</button>
    </div>

    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-4">
        <h3 class="font-semibold mb-3">&#10024; Setup status</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($tiles as [$label, $state, $note])
                <div class="rounded-2xl ring-1 p-3 flex gap-2 {{ $tone[$state] }}">
                    <span class="shrink-0 w-6 h-6 rounded-full grid place-items-center text-xs font-extrabold {{ $dot[$state] }}">{!! $icon[$state] !!}</span>
                    <div class="min-w-0"><div class="text-xs font-extrabold uppercase tracking-wide">{{ $label }}</div><div class="text-xs mt-0.5 break-words">{{ $note }}</div></div>
                </div>
            @endforeach
        </div>
        @if ($network->isNotEmpty() && ($waiting || $failed))
            <p class="mt-3 text-sm {{ $failed ? 'text-red-700' : 'text-amber-800' }}">{{ $waiting }} slip(s) waiting to print{{ $failed ? ', ' . $failed . ' failed today (printer off, out of paper or wrong IP)' : '' }}.</p>
        @endif
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
        @forelse ($printers as $p)
            <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-4 {{ $p->active ? '' : 'opacity-60' }}">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 shrink-0 rounded-xl bg-orange-100 text-orange-600 grid place-items-center text-xl">&#128424;</div>
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-lg truncate">{{ $p->name }} @unless ($p->active)<span class="text-xs font-semibold text-gray-500">(off)</span>@endunless</div>
                        <div class="text-sm text-gray-600">{{ Printer::ROLES[$p->role] }}</div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5 text-xs font-semibold">
                    @if ($p->connection === 'network')
                        <span class="rounded-full bg-blue-50 text-blue-800 px-2 py-1">WiFi / LAN {{ $p->ip }}:{{ $p->port }}</span>
                    @else
                        <span class="rounded-full bg-gray-100 text-gray-700 px-2 py-1">USB / Bluetooth on the device</span>
                    @endif
                    @if ($p->doesKot())
                        <span class="rounded-full bg-amber-50 text-amber-800 px-2 py-1">KOT: {{ empty($p->stations) ? 'all dishes' : collect($p->stations)->map(fn ($s) => Printer::STATIONS[$s] ?? $s)->implode(', ') }}</span>
                    @endif
                    @if ($p->auto_receipt)<span class="rounded-full bg-emerald-50 text-emerald-800 px-2 py-1">Receipt after payment</span>@endif
                    @if ($p->connection === 'network')<span class="rounded-full bg-gray-100 text-gray-700 px-2 py-1">{{ $p->printed_today }} printed today</span>@endif
                </div>
                <div class="mt-4 flex gap-2">
                    @if ($p->connection === 'network')
                        <form method="POST" action="{{ route('manage.printers.test', $p) }}" class="flex-1">@csrf<button class="w-full rounded-xl bg-espresso text-crema font-semibold py-2 text-sm hover:bg-roast">Test print</button></form>
                    @else
                        <a href="{{ route('manage.printers.testpage') }}" target="_blank" class="flex-1 text-center rounded-xl bg-espresso text-crema font-semibold py-2 text-sm hover:bg-roast">Test page</a>
                    @endif
                    <button type="button" onclick='openPrinter(@json($p))' class="rounded-xl ring-1 ring-black/10 px-4 py-2 text-sm font-semibold hover:bg-gray-50">Edit</button>
                    <form method="POST" action="{{ route('manage.printers.destroy', $p) }}" onsubmit="return confirm('Remove {{ addslashes($p->name) }}?')">@csrf @method('DELETE')<button class="rounded-xl ring-1 ring-red-200 text-red-700 px-3 py-2 text-sm font-semibold hover:bg-red-50">Remove</button></form>
                </div>
            </div>
        @empty
            <div class="sm:col-span-2 bg-white rounded-2xl shadow ring-1 ring-black/5 p-8 text-center">
                <div class="text-4xl">&#128424;</div>
                <p class="mt-2 font-semibold">No printers yet</p>
                <p class="text-sm text-gray-600">Add your counter printer first. Most thermal printers on WiFi or a network cable use port 9100.</p>
                <button type="button" onclick="openPrinter()" class="mt-3 rounded-full bg-orange-500 hover:bg-orange-600 text-white font-bold px-5 py-2">+ Add printer</button>
            </div>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl shadow ring-1 ring-black/5 p-4">
        <h3 class="font-semibold">&#128225; Print bridge (for WiFi / network printers)</h3>
        <p class="text-sm text-gray-600 mt-1">Phones can't talk to a WiFi printer directly, so one computer at the cafe passes slips from the POS to the printers. It must be on the same WiFi as the printers and stay switched on during service.</p>
        <ol class="mt-3 space-y-1 text-sm list-decimal pl-5">
            <li>On that computer, sign in here and download the bridge below.</li>
            <li>Windows: double-click <b>tele-print-bridge.cmd</b> and leave the window open. (If Windows warns, choose <i>More info &rarr; Run anyway</i>.)</li>
            <li>Come back to this page: <b>Print bridge</b> above turns green. Then tap <b>Test print</b> on each printer.</li>
        </ol>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('manage.printers.bridge', 'windows') }}" class="rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold px-4 py-2 text-sm">Download for Windows</a>
            <a href="{{ route('manage.printers.bridge', 'python') }}" class="rounded-xl ring-1 ring-black/10 px-4 py-2 text-sm font-semibold hover:bg-gray-50">Linux / Mac / Android (Python)</a>
        </div>
        <p class="mt-2 text-xs text-gray-500">The download contains this cafe's private print key. Keep it on the cafe computer only.</p>
    </div>
</div>

<div id="printer-modal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-black/50 grid place-items-center p-4" onclick="if (event.target === this) this.classList.add('hidden')">
    <form id="printer-form" method="POST" action="{{ old('_printer') ? route('manage.printers.update', old('_printer')) : route('manage.printers.store') }}" class="w-full max-w-md bg-white rounded-3xl shadow-xl p-5 space-y-4 max-h-[92vh] overflow-y-auto">
        @csrf
        <input type="hidden" name="_method" id="pf-method" value="{{ old('_printer') ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_printer" id="pf-id" value="{{ old('_printer') }}">
        <div class="flex items-center justify-between"><h3 id="pf-title" class="font-display text-xl font-extrabold">{{ old('_printer') ? 'Edit printer' : 'Add printer' }}</h3><button type="button" onclick="document.getElementById('printer-modal').classList.add('hidden')" class="text-2xl leading-none text-gray-400">&times;</button></div>
        @if ($errors->any())<div class="rounded-xl bg-red-50 text-red-800 text-sm p-3">{{ $errors->first() }}</div>@endif
        <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">Name</span>
            <input name="name" id="pf-name" required maxlength="60" value="{{ old('name') }}" placeholder="e.g. Counter printer" class="mt-1 w-full border rounded-xl px-3 py-3"></label>
        <div><span class="text-xs font-bold uppercase tracking-wider text-gray-500">What does it print?</span>
            <div class="mt-1 grid gap-1.5">
                @foreach (Printer::ROLES as $k => $v)
                    <label class="cursor-pointer"><input type="radio" name="role" value="{{ $k }}" class="peer sr-only" @checked(old('role', 'both') === $k) onchange="pfSync()"><span class="block rounded-xl ring-1 ring-black/10 px-3 py-2.5 text-sm font-semibold peer-checked:bg-espresso peer-checked:text-crema">{{ $v }}</span></label>
                @endforeach
            </div></div>
        <div><span class="text-xs font-bold uppercase tracking-wider text-gray-500">How is it connected?</span>
            <div class="mt-1 grid grid-cols-2 gap-1.5">
                <label class="cursor-pointer"><input type="radio" name="connection" value="network" class="peer sr-only" @checked(old('connection', 'network') === 'network') onchange="pfSync()"><span class="block rounded-xl ring-1 ring-black/10 px-3 py-2.5 text-sm font-semibold text-center peer-checked:bg-espresso peer-checked:text-crema">WiFi / network cable</span></label>
                <label class="cursor-pointer"><input type="radio" name="connection" value="device" class="peer sr-only" @checked(old('connection') === 'device') onchange="pfSync()"><span class="block rounded-xl ring-1 ring-black/10 px-3 py-2.5 text-sm font-semibold text-center peer-checked:bg-espresso peer-checked:text-crema">USB / Bluetooth</span></label>
            </div></div>
        <div id="pf-net" class="grid grid-cols-3 gap-2">
            <label class="col-span-2 block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">IP address</span>
                <input name="ip" id="pf-ip" value="{{ old('ip') }}" placeholder="192.168.1.50" inputmode="decimal" class="mt-1 w-full border rounded-xl px-3 py-3 font-mono"></label>
            <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">Port</span>
                <input name="port" id="pf-port" value="{{ old('port', 9100) }}" inputmode="numeric" class="mt-1 w-full border rounded-xl px-3 py-3 font-mono"></label>
            <p class="col-span-3 text-xs text-gray-500">Hold the printer's feed button while switching it on: it prints a self-test page showing its IP address.</p>
        </div>
        <p id="pf-dev" class="hidden text-xs text-gray-600 bg-gray-50 rounded-xl p-3">Pair or plug the printer into the phone or tablet and make it the default printer there. Slips open the device print dialog, as now.</p>
        <div id="pf-kot"><span class="text-xs font-bold uppercase tracking-wider text-gray-500">Kitchen tickets for (station routing)</span>
            <div class="mt-1 flex flex-wrap gap-1.5">
                @foreach (Printer::STATIONS as $k => $v)
                    <label class="cursor-pointer"><input type="checkbox" name="stations[]" value="{{ $k }}" class="peer sr-only pf-st" @checked(in_array($k, old('stations', array_keys(Printer::STATIONS))))><span class="block rounded-full ring-1 ring-black/10 px-3 py-1.5 text-sm font-semibold peer-checked:bg-amber-400 peer-checked:ring-amber-400">{{ $v }}</span></label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-1">With a second printer in a separate kitchen or bar, untick the dishes it shouldn't get.</p></div>
        <label id="pf-auto" class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_receipt" value="1" id="pf-autor" @checked(old('auto_receipt')) class="w-5 h-5"> Print a receipt by itself after each payment</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" id="pf-active" @checked(old('active', 1)) class="w-5 h-5"> Printer is on (untick to pause it)</label>
        <button class="w-full rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold py-3">Save printer</button>
    </form>
</div>

<script>
    var pfBase = @json(url('/manage/printers'));
    function pfSync() {
        var f = document.getElementById('printer-form');
        var role = (f.querySelector('[name=role]:checked') || {}).value, net = (f.querySelector('[name=connection]:checked') || {}).value === 'network';
        document.getElementById('pf-net').classList.toggle('hidden', !net);
        document.getElementById('pf-dev').classList.toggle('hidden', net);
        document.getElementById('pf-kot').classList.toggle('hidden', role === 'bill');
        document.getElementById('pf-auto').classList.toggle('hidden', !net || role === 'kot');
    }
    function openPrinter(p) {
        var f = document.getElementById('printer-form');
        f.reset();
        document.getElementById('pf-title').textContent = p ? 'Edit printer' : 'Add printer';
        f.action = p ? pfBase + '/' + p.id : pfBase;
        document.getElementById('pf-method').value = p ? 'PUT' : 'POST';
        document.getElementById('pf-id').value = p ? p.id : '';
        document.getElementById('pf-name').value = p ? p.name : '';
        document.getElementById('pf-ip').value = p && p.ip ? p.ip : '';
        document.getElementById('pf-port').value = p ? p.port : 9100;
        f.querySelectorAll('[name=role]').forEach(function (r) { r.checked = r.value === (p ? p.role : 'both'); });
        f.querySelectorAll('[name=connection]').forEach(function (r) { r.checked = r.value === (p ? p.connection : 'network'); });
        f.querySelectorAll('.pf-st').forEach(function (c) { c.checked = !p || !p.stations || p.stations.indexOf(c.value) > -1; });
        document.getElementById('pf-autor').checked = !!(p && p.auto_receipt);
        document.getElementById('pf-active').checked = !p || !!p.active;
        f.querySelectorAll('.bg-red-50').forEach(function (e) { e.remove(); });
        pfSync();
        document.getElementById('printer-modal').classList.remove('hidden');
    }
    pfSync();
</script>
@endsection
