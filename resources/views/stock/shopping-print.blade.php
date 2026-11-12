@php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping list {{ now()->format('j M') }} &mdash; {{ config('pos.cafe_name') }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 1.35; color: #000; background: #fff;
               width: 72mm; margin: 0 auto; padding: 4mm 0 6mm; }
        .c { text-align: center; } .b { font-weight: bold; } .big { font-size: 17px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        .item { padding: 4px 0; border-bottom: 1px dotted #000; }
        .name { font-size: 14px; font-weight: bold; }
        .row { display: flex; justify-content: space-between; }
        .blank { display: inline-block; min-width: 26mm; border-bottom: 1px solid #000; }
        .money div { margin: 7px 0; }
        .noprint { margin: 14px 0; text-align: center; font-family: sans-serif; font-size: 14px; }
        .noprint button { padding: 6px 16px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="c b big">{{ config('pos.cafe_name') }}</div>
    <div class="c b">SHOPPING LIST</div>
    <div class="c">{{ now()->format('D j M Y H:i') }} &middot; {{ $by }}</div>
    <hr>
    @forelse ($toBuy as $b)
        <div class="item">
            <div class="name">[ ] {{ $b->item->name }}</div>
            <div class="row"><span>Buy: <b>{{ $b->suggest !== null ? $q($b->suggest) . ' ' . $b->item->unit : 'restock' }}</b></span><span>left {{ $q($b->qty) }}</span></div>
            <div class="row"><span>Bought: <span class="blank"></span></span></div>
            <div class="row"><span>Price KSh: <span class="blank"></span></span></div>
        </div>
    @empty
        <div class="c">Nothing is below its reorder level.</div>
    @endforelse
    <hr>
    <div>Items: {{ $toBuy->count() }}</div>
    <div class="money">
        <div>Money given KSh: <span class="blank"></span></div>
        <div>Total spent KSh: <span class="blank"></span></div>
        <div>Change back KSh: <span class="blank"></span></div>
        <div>Signed: <span class="blank"></span></div>
    </div>

    <div class="noprint">
        <button onclick="window.print()">Print</button>
        &nbsp; <a href="{{ route('stock.count') }}">Back to stock</a>
    </div>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
