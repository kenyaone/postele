<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }} &mdash; {{ config('pos.cafe_name') }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 1.35; color: #000; background: #fff;
               width: 72mm; margin: 0 auto; padding: 4mm 0; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .big { font-size: 17px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        td.r { text-align: right; white-space: nowrap; padding-left: 6px; }
        .noprint { margin: 14px 0; text-align: center; font-family: sans-serif; font-size: 14px; }
        .noprint button { padding: 6px 16px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="c b big">{{ config('pos.cafe_name') }}</div>
    <div class="c">Till {{ config('pos.till_number') }} &middot; {{ config('pos.phone') }}</div>
    <hr>
    <div class="c b">BILL &mdash; PLEASE PAY</div>
    <hr>
    <div>Ticket #{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</div>
    <div>{{ $ticket->opened_at?->format('d M Y H:i') }}</div>
    <div>Cashier: {{ $ticket->opener->name ?? '?' }}</div>
    <hr>
    <table>
        @foreach ($ticket->items as $line)
            <tr>
                <td>{{ $line->qty }} x {{ $line->item->name }}</td>
                <td class="r">{{ number_format($line->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr class="b big">
            <td>TOTAL</td>
            <td class="r">KSh {{ number_format($ticket->total, 2) }}</td>
        </tr>
    </table>
    <hr>
    <div class="c">Pay at the counter.</div>
    <div class="c b">M-Pesa Till {{ config('pos.till_number') }}</div>
    <div class="c">(Not a receipt)</div>

    <div class="noprint">
        <button onclick="window.print()">Print</button>
        &nbsp; <a href="{{ route('pos.tickets.show', $ticket) }}">Back to ticket</a>
    </div>
    <script>
        // Opened directly (not inside the POS's hidden frame): show the print dialog on load.
        if (window.self === window.top) { window.addEventListener('load', function () { window.print(); }); }
    </script>
</body>
</html>
