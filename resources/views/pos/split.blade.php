@php $no = $ticket->order_no; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Split bill #{{ $no }} - {{ config('pos.cafe_name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { espresso: '#2b1b14', crema: '#f7efe4', roast: '#6b3f26', saffron: '#f5a524' } } } };</script>
</head>
<body class="bg-crema text-espresso min-h-screen antialiased" style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif">
<header class="bg-espresso text-crema px-4 py-3 flex items-center gap-3 shadow-lg">
<a href="{{ route('pos.tickets.show', $ticket) }}" class="rounded-xl px-3 py-2 hover:bg-white/10 font-semibold">&larr; Back</a>
<div class="font-bold text-lg">Split bill #{{ $no }}</div>
</header>
<main class="max-w-xl mx-auto p-4">
@if ($errors->any())<div class="rounded-xl bg-red-50 ring-1 ring-red-200 text-red-800 px-4 py-2.5 text-sm font-medium mb-3">&#9888; {{ $errors->first() }}</div>@endif
<p class="text-sm text-roast/80 mb-3">Tap <b>+</b> on the dishes that go on the <b>new bill</b> (for example, what one person had). Everything else stays on bill #{{ $no }}. Dishes already sent to the kitchen are not sent again.</p>
<form method="POST" action="{{ route('pos.tickets.split.store', $ticket) }}" class="space-y-3">
@csrf
<div class="rounded-2xl bg-white ring-1 ring-roast/10 divide-y divide-dashed divide-roast/20">
@foreach ($ticket->items as $line)
<div class="flex items-center gap-3 px-4 py-3" data-line data-price="{{ (float) $line->unit_price }}" data-max="{{ (int) $line->qty }}">
<div class="flex-1 min-w-0"><div class="font-semibold truncate">{{ $line->item->name ?? 'Dish' }}</div><div class="text-xs text-roast/60">{{ $line->qty }} on the bill &middot; KSh {{ number_format((float) $line->unit_price) }} each</div></div>
<div class="flex items-center rounded-full bg-crema ring-1 ring-roast/15">
<button type="button" data-step="-1" class="w-12 h-12 rounded-full text-xl font-bold hover:bg-white" title="One less">&minus;</button>
<input type="number" name="move[{{ $line->id }}]" value="{{ (int) old('move.' . $line->id, 0) }}" min="0" max="{{ (int) $line->qty }}" inputmode="numeric" class="w-12 text-center text-lg font-extrabold bg-transparent focus:outline-none">
<button type="button" data-step="1" class="w-12 h-12 rounded-full text-xl font-bold hover:bg-white" title="One more">+</button>
</div>
</div>
@endforeach
</div>
<div class="grid grid-cols-2 gap-3">
<div class="rounded-2xl bg-white ring-1 ring-roast/10 p-3 text-center"><div class="text-xs uppercase tracking-wider text-roast/60">Stays on #{{ $no }}</div><div class="text-2xl font-extrabold">KSh <span id="stay">{{ number_format($ticket->total) }}</span></div></div>
<div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-3 text-center"><div class="text-xs uppercase tracking-wider text-amber-800">New bill</div><div class="text-2xl font-extrabold">KSh <span id="moved">0</span></div></div>
</div>
<button id="split-go" disabled class="w-full rounded-2xl bg-saffron hover:bg-amber-400 text-espresso font-extrabold text-lg py-4 shadow-md disabled:opacity-40 disabled:cursor-not-allowed">&#9986; Move to a new bill</button>
<a href="{{ route('pos.tickets.show', $ticket) }}" class="block text-center text-sm text-roast hover:underline">Cancel, keep it as one bill</a>
</form>
</main>
<script>
(function () {
var total = {{ (float) $ticket->total }};
var rows = document.querySelectorAll('[data-line]');
function refresh() {
var moved = 0, movedQty = 0, allQty = 0;
rows.forEach(function (r) {
var inp = r.querySelector('input'), max = parseInt(r.dataset.max, 10);
var v = Math.max(0, Math.min(max, parseInt(inp.value, 10) || 0));
inp.value = v; moved += v * parseFloat(r.dataset.price); movedQty += v; allQty += max;
});
document.getElementById('moved').textContent = Math.round(moved).toLocaleString();
document.getElementById('stay').textContent = Math.round(total - moved).toLocaleString();
document.getElementById('split-go').disabled = movedQty < 1 || movedQty >= allQty;
}
rows.forEach(function (r) {
r.querySelectorAll('[data-step]').forEach(function (b) {
b.addEventListener('click', function () { var inp = r.querySelector('input'); inp.value = (parseInt(inp.value, 10) || 0) + parseInt(b.dataset.step, 10); refresh(); });
});
r.querySelector('input').addEventListener('input', refresh);
});
refresh();
})();
</script>
</body>
</html>
