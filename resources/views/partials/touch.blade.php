{{-- Touch-screen helpers: no double-tap zoom or text selection; on-screen keypad for input[data-keypad="money|phone|pin"] on touch screens only. --}}
<style>
    html { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
    button, a, .tile, label { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
    #kp { transition: transform .18s ease-out; }
    #kp.kp-hidden { transform: translateY(115%); }
</style>
<div id="kp" class="kp-hidden fixed inset-x-0 bottom-0 z-50 flex justify-center pointer-events-none" aria-hidden="true">
    <div class="pointer-events-auto w-full max-w-sm m-2 rounded-3xl p-3 shadow-2xl" style="background:#2b1b14;color:#f7efe4">
        <div class="flex items-center justify-between gap-3 px-2 pb-2">
            <span id="kp-label" class="text-xs uppercase tracking-wider opacity-70"></span>
            <span id="kp-value" class="text-3xl font-bold tracking-wider truncate"></span>
        </div>
        <div id="kp-keys" class="grid grid-cols-3 gap-2"></div>
        <div class="grid grid-flow-col auto-cols-fr gap-2 mt-2">
            <button type="button" data-k="clear" class="h-14 rounded-2xl text-lg font-semibold" style="background:rgba(255,255,255,.1)">Clear</button>
            <button type="button" data-k="abc" id="kp-abc" class="hidden h-14 rounded-2xl text-lg font-semibold" style="background:rgba(255,255,255,.1)">ABC</button>
            <button type="button" data-k="done" class="h-14 rounded-2xl text-lg font-extrabold" style="background:#f5a524;color:#2b1b14">Done</button>
        </div>
    </div>
</div>
<script>
(function () {
    var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    var inputs = document.querySelectorAll('input[data-keypad]');
    if (!touch || !inputs.length) return;
    var kp = document.getElementById('kp'), keys = document.getElementById('kp-keys'),
        val = document.getElementById('kp-value'), lab = document.getElementById('kp-label'), cur = null, fresh = false;
    var layouts = {
        money: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0', 'del'],
        phone: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '+', '0', 'del'],
        pin:   ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'del']
    };
    function show() {
        var t = cur.dataset.keypad;
        val.textContent = t === 'pin' ? cur.value.replace(/./g, '*') : (cur.value || (t === 'money' ? '0' : ''));
    }
    function build(t) {
        keys.innerHTML = '';
        layouts[t].forEach(function (k) {
            var b = document.createElement('button');
            b.type = 'button';
            if (k === '') { b.disabled = true; b.className = 'h-16'; }
            else { b.dataset.k = k; b.className = 'h-16 rounded-2xl text-2xl font-bold'; b.style.background = 'rgba(255,255,255,.1)'; b.innerHTML = k === 'del' ? '&#9003;' : k; }
            keys.appendChild(b);
        });
    }
    function open(inp) {
        if (!inp.dataset.keypad) return;
        document.getElementById('kp-abc').classList.toggle('hidden', inp.dataset.keypadText !== '1');
        cur = inp; fresh = true; build(inp.dataset.keypad || 'money');
        lab.textContent = inp.dataset.keypadLabel || inp.getAttribute('placeholder') || '';
        show(); kp.classList.remove('kp-hidden'); kp.setAttribute('aria-hidden', 'false');
    }
    function close() { kp.classList.add('kp-hidden'); kp.setAttribute('aria-hidden', 'true'); cur = null; }
    function fire() { cur.dispatchEvent(new Event('input', { bubbles: true })); show(); }
    kp.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-k]');
        if (!b || !cur) return;
        var k = b.dataset.k, max = parseInt(cur.getAttribute('maxlength') || '0', 10) || 13;
        if (k === 'done') {
            var f = cur.form, t = cur.dataset.keypad;
            close();
            if (t === 'pin' && f) { if (f.requestSubmit) f.requestSubmit(); else f.submit(); }
            return;
        }
        if (k === 'abc') { var i = cur; close(); i.removeAttribute('readonly'); i.setAttribute('inputmode', 'text'); i.dataset.keypad = ''; i.focus(); return; }
        if (k === 'clear') { cur.value = ''; fresh = false; fire(); return; }
        if (k === 'del') { cur.value = cur.value.slice(0, -1); fresh = false; fire(); return; }
        if (fresh && cur.dataset.keypad === 'money') cur.value = '';
        fresh = false;
        if (cur.value.length >= max) return;
        cur.value += k; fire();
    });
    inputs.forEach(function (inp) {
        inp.setAttribute('inputmode', 'none');
        inp.setAttribute('readonly', 'readonly');
        inp.style.cursor = 'pointer';
        inp.addEventListener('click', function () { open(inp); });
    });
    document.addEventListener('click', function (e) {
        if (cur && !kp.contains(e.target) && e.target !== cur) close();
    });
})();
</script>
