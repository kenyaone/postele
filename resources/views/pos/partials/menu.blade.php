@php
    $canAdd = $ticket && $ticket->status === 'open';
    $cats = $categories->filter(fn ($c) => $c->items->isNotEmpty());
@endphp
<div class="sticky top-0 z-10 bg-gray-100 pt-1 pb-2">
    <div class="relative">
        <span class="absolute left-3 top-2 text-gray-400 pointer-events-none">&#128269;</span>
        <input id="menu-search" type="search" autocomplete="off" placeholder="Search the menu (press / )"
               class="w-full border rounded-lg pl-9 pr-3 py-2 text-base focus:outline-none focus:ring-2 focus:ring-emerald-500">
    </div>
    <div id="cat-chips" class="flex gap-2 overflow-x-auto mt-2 pb-1">
        <button type="button" data-cat="all" class="cat-chip whitespace-nowrap text-sm rounded-full px-3 py-1 border border-emerald-600 bg-white">All</button>
        @foreach ($cats as $cat)
            <button type="button" data-cat="{{ $cat->id }}" class="cat-chip whitespace-nowrap text-sm rounded-full px-3 py-1 border border-emerald-600 bg-white">{{ $cat->name }} <span class="opacity-60">{{ $cat->items->count() }}</span></button>
        @endforeach
    </div>
</div>

@if ($ticket && ! $canAdd)
    <p class="text-sm text-gray-500 mb-2">This ticket has been sent to the kitchen, so items can no longer be added.</p>
@endif

<div id="menu-items">
    @foreach ($cats as $cat)
        <section class="menu-cat mb-4" data-cat="{{ $cat->id }}">
            <h2 class="font-semibold text-emerald-800 mb-2">{{ $cat->name }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2">
                @foreach ($cat->items as $item)
                    <form method="POST" action="{{ $ticket ? route('pos.tickets.items.store', $ticket) : '#' }}" class="menu-item" data-name="{{ mb_strtolower($item->name) }}">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <button type="submit" {{ $canAdd ? '' : 'disabled' }}
                                class="w-full h-full bg-white rounded-lg shadow-sm border border-transparent hover:border-emerald-400 hover:bg-emerald-50 active:bg-emerald-100 disabled:opacity-40 disabled:cursor-not-allowed px-3 py-2 text-left">
                            <div class="font-medium text-sm leading-tight">{{ $item->name }}</div>
                            <div class="text-emerald-700 font-bold text-sm">KSh {{ number_format((float) $item->price) }}</div>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    @endforeach
    <p id="menu-empty" class="hidden text-center text-gray-500 py-8">Nothing on the menu matches &ldquo;<span></span>&rdquo;.</p>
</div>

@if ($ticket && $ticket->items->isNotEmpty())
    <a href="#ticket-panel" class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-emerald-700 text-white px-4 py-3 flex justify-between font-semibold shadow-lg">
        <span>Ticket #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }} &middot; {{ $ticket->items->sum('qty') }} item(s)</span>
        <span>KSh {{ number_format($ticket->total, 2) }} &rarr;</span>
    </a>
    <div class="lg:hidden h-14"></div>
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
            var catOk = q !== '' || st.cat === 'all' || sec.dataset.cat === st.cat;   // a search looks across every category
            var shown = 0;
            sec.querySelectorAll('.menu-item').forEach(function (it) {
                var ok = catOk && (q === '' || it.dataset.name.indexOf(q) !== -1);
                it.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            sec.style.display = shown ? '' : 'none';
            if (shown) any = true;
        });
        chips.forEach(function (c) {
            var on = q === '' && c.dataset.cat === st.cat;
            c.classList.toggle('bg-emerald-600', on); c.classList.toggle('text-white', on); c.classList.toggle('bg-white', !on);
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
        c.addEventListener('click', function () {
            st.cat = c.dataset.cat; st.q = ''; input.value = ''; apply();
            if (col) col.scrollTop = 0;
        });
    });
    document.addEventListener('keydown', function (e) {
        var tag = (document.activeElement || {}).tagName || '';
        if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) { e.preventDefault(); input.focus(); }
    });

    function saveScroll() { try { sessionStorage.setItem('pos-scroll', JSON.stringify({ c: col ? col.scrollTop : 0, w: window.scrollY })); } catch (e) {} }
    document.querySelectorAll('.menu-item').forEach(function (f) { f.addEventListener('submit', saveScroll); });
    try {
        var s = JSON.parse(sessionStorage.getItem('pos-scroll') || 'null');
        if (s) { if (col) col.scrollTop = s.c; window.scrollTo(0, s.w); sessionStorage.removeItem('pos-scroll'); }
    } catch (e) {}

    apply();
})();
</script>
