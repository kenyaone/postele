@php
    $canAdd = $ticket && $ticket->status === 'open';
    $cats = $categories->filter(fn ($c) => $c->items->isNotEmpty())->values();
    $palette = [
        ['on' => 'bg-amber-500 text-white ring-amber-500', 'band' => 'bg-amber-400', 'soft' => 'bg-amber-100 text-amber-800'],
        ['on' => 'bg-emerald-600 text-white ring-emerald-600', 'band' => 'bg-emerald-500', 'soft' => 'bg-emerald-100 text-emerald-800'],
        ['on' => 'bg-sky-600 text-white ring-sky-600', 'band' => 'bg-sky-500', 'soft' => 'bg-sky-100 text-sky-800'],
        ['on' => 'bg-rose-600 text-white ring-rose-600', 'band' => 'bg-rose-500', 'soft' => 'bg-rose-100 text-rose-800'],
        ['on' => 'bg-violet-600 text-white ring-violet-600', 'band' => 'bg-violet-500', 'soft' => 'bg-violet-100 text-violet-800'],
        ['on' => 'bg-orange-600 text-white ring-orange-600', 'band' => 'bg-orange-500', 'soft' => 'bg-orange-100 text-orange-800'],
        ['on' => 'bg-teal-600 text-white ring-teal-600', 'band' => 'bg-teal-500', 'soft' => 'bg-teal-100 text-teal-800'],
        ['on' => 'bg-lime-600 text-white ring-lime-600', 'band' => 'bg-lime-500', 'soft' => 'bg-lime-100 text-lime-800'],
        ['on' => 'bg-fuchsia-600 text-white ring-fuchsia-600', 'band' => 'bg-fuchsia-500', 'soft' => 'bg-fuchsia-100 text-fuchsia-800'],
        ['on' => 'bg-cyan-600 text-white ring-cyan-600', 'band' => 'bg-cyan-500', 'soft' => 'bg-cyan-100 text-cyan-800'],
    ];
    $icon = function ($name) {
        $n = strtolower($name);
        foreach ([['chapati', '&#129747;'], ['chapo', '&#129747;'], ['mandazi', '&#127849;'], ['samosa', '&#129375;'], ['ugali', '&#127834;'], ['rice', '&#127834;'], ['ndengu', '&#129752;'], ['beans', '&#129752;'],
                  ['sausage', '&#127789;'], ['smokie', '&#127789;'], ['chicken', '&#127831;'], ['kuku', '&#127831;'], ['chips', '&#127839;'], ['fries', '&#127839;'], ['omelet', '&#127859;'], ['egg', '&#127859;'], ['mayai', '&#127859;'],
                  ['pork', '&#129385;'], ['beef', '&#129385;'], ['fish', '&#128031;'], ['tea', '&#9749;'], ['chai', '&#9749;'], ['coffee', '&#9749;'], ['hot', '&#9749;'], ['cold', '&#129380;'], ['soda', '&#129380;'],
                  ['juice', '&#129380;'], ['water', '&#129380;'], ['drink', '&#129380;'], ['snack', '&#129360;'], ['side', '&#129367;'], ['sukuma', '&#129367;'], ['light', '&#129386;'], ['main', '&#127835;'], ['dish', '&#127835;']] as [$k, $e]) {
            if (str_contains($n, $k)) {
                return $e;
            }
        }
        return '&#127869;';
    };
@endphp
<div class="sticky top-0 z-10 -mx-1 px-1 pt-1 pb-3 bg-crema/95 backdrop-blur">
    <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-roast/50 pointer-events-none">&#128269;</span>
        <input id="menu-search" type="search" autocomplete="off" placeholder="Search dishes..."
               class="w-full rounded-2xl bg-white pl-11 pr-4 py-3 text-base shadow-sm ring-1 ring-roast/15 focus:outline-none focus:ring-2 focus:ring-saffron">
    </div>
    <div id="cat-chips" class="flex gap-2 overflow-x-auto mt-3 pb-1">
        <button type="button" data-cat="all" data-on="bg-espresso text-crema ring-espresso" class="cat-chip shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold bg-white ring-1 ring-roast/15 shadow-sm">&#11088; All</button>
        @foreach ($cats as $ci => $cat)
            @php $pc = $palette[$ci % count($palette)]; @endphp
            <button type="button" data-cat="{{ $cat->id }}" data-on="{{ $pc['on'] }}" class="cat-chip shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold bg-white ring-1 ring-roast/15 shadow-sm">{!! $icon($cat->name) !!} {{ $cat->name }} <span class="opacity-60 font-normal">{{ $cat->items->count() }}</span></button>
        @endforeach
    </div>
</div>

@if ($ticket && ! $canAdd)
    <div class="mb-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 text-amber-900 text-sm px-4 py-2">{{ $ticket->status === 'paid' ? 'This order is paid. Tap + New order for the next customer.' : 'This order is already with the kitchen. Start a new order to add more dishes.' }}</div>
@endif

<div id="menu-items" class="space-y-6">
    @foreach ($cats as $ci => $cat)
        @php $pc = $palette[$ci % count($palette)]; $catIcon = $icon($cat->name); @endphp
        <section class="menu-cat" data-cat="{{ $cat->id }}">
            <h2 class="flex items-center gap-2 font-display text-lg font-bold text-espresso mb-2">
                <span class="w-8 h-8 rounded-xl grid place-items-center text-base {{ $pc['soft'] }}">{!! $catIcon !!}</span>{{ $cat->name }}
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5">
                @foreach ($cat->items as $item)
                    @php
                        $ic = $icon($item->name);
                        if ($ic === '&#127869;') { $ic = $catIcon; }
                        $inTicket = $ticket ? (int) $ticket->items->where('item_id', $item->id)->sum('qty') : 0;
                    @endphp
                    <form method="POST" action="{{ $ticket ? route('pos.tickets.items.store', $ticket) : '#' }}" class="menu-item" data-name="{{ mb_strtolower($item->name) }}">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <button type="submit" {{ $canAdd ? '' : 'disabled' }}
                                class="tile relative w-full h-full min-h-[96px] text-left rounded-2xl bg-white shadow-sm ring-1 ring-roast/10 hover:ring-2 hover:ring-saffron hover:shadow-md overflow-hidden pl-4 pr-3 py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="absolute left-0 inset-y-0 w-1.5 {{ $pc['band'] }}"></span>
                            <span class="tile-top flex items-start justify-between gap-2">
                                <span class="text-2xl leading-none">{!! $ic !!}</span>
                                @if ($inTicket)<span class="qty-badge pop min-w-[1.75rem] h-7 px-2 rounded-full bg-espresso text-crema text-sm font-bold grid place-items-center" title="Already on this order">{{ $inTicket }}</span>@endif
                            </span>
                            <span class="tile-name block font-semibold leading-snug mt-2">{{ $item->name }}</span>
                            <span class="block text-sm font-bold text-roast mt-0.5">KSh {{ number_format((float) $item->price) }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    @endforeach
    <p id="menu-empty" class="hidden text-center text-roast/60 py-10">&#129300; Nothing on the menu matches &ldquo;<span></span>&rdquo;.</p>
</div>

@if ($ticket && $ticket->items->isNotEmpty() && $ticket->status !== 'paid')
    <a href="#ticket-panel" class="lg:hidden fixed bottom-3 inset-x-3 z-20 rounded-2xl bg-espresso text-crema px-5 py-3.5 flex items-center justify-between font-semibold shadow-2xl">
        <span class="flex items-center gap-2"><span class="bg-saffron text-espresso rounded-full min-w-[1.75rem] h-7 px-1 grid place-items-center text-sm font-extrabold">{{ $ticket->items->sum('qty') }}</span> View order #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</span>
        <span class="font-display text-lg">KSh {{ number_format($ticket->total) }} &rarr;</span>
    </a>
    <div class="lg:hidden h-20"></div>
@endif

<script>
(function () {
    var input = document.getElementById('menu-search'), col = document.getElementById('menu-col'),
        chips = document.querySelectorAll('.cat-chip'), cats = document.querySelectorAll('.menu-cat'),
        empty = document.getElementById('menu-empty'), st = { q: '', cat: 'all' };
    try { var saved = JSON.parse(sessionStorage.getItem('pos-menu') || '{}'); if (saved.cat) st.cat = saved.cat; if (saved.q) st.q = saved.q; } catch (e) {}
    if (st.cat !== 'all' && !document.querySelector('.menu-cat[data-cat="' + st.cat + '"]')) st.cat = 'all';

    function apply() {
        var q = st.q.trim().toLowerCase(), any = false;
        cats.forEach(function (sec) {
            var catOk = q !== '' || st.cat === 'all' || sec.dataset.cat === st.cat, shown = 0;
            sec.querySelectorAll('.menu-item').forEach(function (it) {
                var ok = catOk && (q === '' || it.dataset.name.indexOf(q) !== -1);
                it.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            sec.style.display = shown ? '' : 'none';
            if (shown) any = true;
        });
        chips.forEach(function (c) {
            var on = q === '' && c.dataset.cat === st.cat, cls = (c.dataset.on || '').split(' ');
            cls.forEach(function (k) { if (k) c.classList.toggle(k, on); });
            c.classList.toggle('bg-white', !on); c.classList.toggle('ring-roast/15', !on);
        });
        empty.classList.toggle('hidden', any);
        empty.querySelector('span').textContent = st.q;
        try { sessionStorage.setItem('pos-menu', JSON.stringify(st)); } catch (e) {}
    }

    input.value = st.q;
    input.addEventListener('input', function () { st.q = input.value; apply(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var vis = [].filter.call(document.querySelectorAll('.menu-item'), function (f) { return f.style.display !== 'none'; });
            if (vis.length === 1 && !vis[0].querySelector('button').disabled) { saveScroll(); vis[0].submit(); }
        }
        if (e.key === 'Escape') { input.value = ''; st.q = ''; apply(); }
    });
    chips.forEach(function (c) {
        c.addEventListener('click', function () { st.cat = c.dataset.cat; st.q = ''; input.value = ''; apply(); if (col) col.scrollTop = 0; });
    });
    document.addEventListener('keydown', function (e) {
        var tag = (document.activeElement || {}).tagName || '';
        if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) { e.preventDefault(); input.focus(); }
    });

    function saveScroll() { try { sessionStorage.setItem('pos-scroll', JSON.stringify({ c: col ? col.scrollTop : 0, w: window.scrollY })); } catch (e) {} }
    var queue = [], busy = false, reloadT = null, toastT = null, toast = document.createElement('div');
    toast.className = 'fixed left-1/2 -translate-x-1/2 bottom-24 lg:bottom-6 z-40 rounded-full bg-espresso text-crema px-5 py-2.5 text-sm font-semibold shadow-xl transition-opacity duration-200 opacity-0 pointer-events-none';
    document.body.appendChild(toast);
    function say(msg) { toast.textContent = msg; toast.style.opacity = '1'; clearTimeout(toastT); toastT = setTimeout(function () { toast.style.opacity = '0'; }, 1200); }
    function bump(f) {
        var top = f.querySelector('.tile-top'), b = f.querySelector('.qty-badge');
        if (!b && top) { b = document.createElement('span'); b.className = 'qty-badge pop min-w-[1.75rem] h-7 px-2 rounded-full bg-espresso text-crema text-sm font-bold grid place-items-center'; b.textContent = '0'; top.appendChild(b); }
        if (b) { b.textContent = String((parseInt(b.textContent, 10) || 0) + 1); b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop'); }
        say('+1  ' + ((f.querySelector('.tile-name') || {}).textContent || 'item').trim());
    }
    function pump(action) {
        if (busy) return;
        var fd = queue.shift();
        if (!fd) { clearTimeout(reloadT); reloadT = setTimeout(function () { saveScroll(); location.reload(); }, 700); return; }
        busy = true; clearTimeout(reloadT);
        fetch(action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { busy = false; if (!r.ok) { saveScroll(); location.reload(); return; } pump(action); })
            .catch(function () { busy = false; saveScroll(); location.reload(); });
    }
    document.querySelectorAll('.menu-item').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.fetch || !window.FormData) { saveScroll(); return; }
            e.preventDefault();
            var btn = f.querySelector('button');
            if (btn && btn.disabled) return;
            bump(f); queue.push(new FormData(f)); pump(f.action);
        });
    });
    try {
        var s = JSON.parse(sessionStorage.getItem('pos-scroll') || 'null');
        if (s) { if (col) col.scrollTop = s.c; window.scrollTo(0, s.w); sessionStorage.removeItem('pos-scroll'); }
    } catch (e) {}
    apply();
})();
</script>
