@php
    // $mode: kot (kitchen only) | both (kitchen + bill) | bill | receipt
    $no = str_pad($ticket->id, 5, '0', STR_PAD_LEFT);
    $methods = ['cash' => 'Cash', 'kk_stk' => 'M-Pesa', 'kk_till_auto' => 'M-Pesa Till', 'kk_till_manual' => 'M-Pesa Till'];
    $title = ['kot' => 'Kitchen', 'both' => 'Kitchen + bill', 'bill' => 'Bill', 'receipt' => 'Receipt', 'cancel' => 'Cancelled'][$mode] ?? 'Bill';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} #{{ $no }} &mdash; {{ config('pos.cafe_name') }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 1.35; color: #000; background: #fff;
               width: 72mm; margin: 0 auto; padding: 0; }
        .slip { padding: 4mm 0 6mm; break-after: page; page-break-after: always; }
        .slip:last-of-type { break-after: auto; page-break-after: auto; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .big { font-size: 17px; }
        .kot td { font-size: 16px; font-weight: bold; padding: 2px 0; }
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
@if ($mode === 'cancel')
    <section class="slip">
        <div class="c b big">*** CANCELLED ***</div>
        <div class="c b big">Ticket #{{ $no }}</div>
        <div class="c">{{ now()->format('d M H:i') }} &middot; {{ $ticket->opener->name ?? '?' }}</div>
        <hr>
        <div class="c b">DO NOT PREPARE</div>
        <hr>
        <table class="kot">
            @foreach ($ticket->items as $line)
                <tr><td style="width: 3.2em">{{ $line->qty }} x</td><td>{{ $line->item->name }}</td></tr>
            @endforeach
        </table>
        @if ($ticket->void_reason)<hr><div>Reason: {{ $ticket->void_reason }}</div>@endif
    </section>
@endif

@if (in_array($mode, ['kot', 'both'], true))
    <section class="slip">
        <div class="c b big">*** KITCHEN ***</div>
        <div class="c b big">Ticket #{{ $no }}</div>
        <div class="c">{{ now()->format('d M H:i') }} &middot; {{ $ticket->opener->name ?? '?' }}</div>
        <hr>
        <table class="kot">
            @foreach ($ticket->items as $line)
                <tr><td style="width: 3.2em">{{ $line->qty }} x</td><td>{{ $line->item->name }}</td></tr>
            @endforeach
        </table>
        @if ($ticket->notes)<hr><div class="b">Notes: {{ $ticket->notes }}</div>@endif
    </section>
@endif

@if (! in_array($mode, ['kot', 'cancel'], true))
    <section class="slip">
        <div class="c b big">{{ config('pos.cafe_name') }}</div>
        <div class="c">Till {{ config('pos.till_number') }} &middot; {{ config('pos.phone') }}</div>
        <hr>
        <div class="c b">{!! $mode === 'receipt' ? 'RECEIPT' : 'BILL &mdash; PLEASE PAY' !!}</div>
        <hr>
        <div>Ticket #{{ $no }}</div>
        <div>{{ $ticket->opened_at?->format('d M Y H:i') }}</div>
        <div>Served by: {{ $ticket->opener->name ?? '?' }}</div>
        <hr>
        <table>
            @foreach ($ticket->items as $line)
                <tr><td>{{ $line->qty }} x {{ $line->item->name }}</td><td class="r">{{ number_format($line->subtotal, 2) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table><tr class="b big"><td>TOTAL</td><td class="r">KSh {{ number_format($ticket->total, 2) }}</td></tr></table>
        <hr>
        @if ($mode === 'receipt' && $payment)
            <div>Paid: {{ $methods[$payment->method] ?? $payment->method }}@if ($payment->kk_reference) ({{ $payment->kk_reference }})@endif</div>
            <div>{{ $payment->received_at?->format('d M Y H:i') }}</div>
            <hr>
            <div class="c">Thank you! Order again:</div>
            <div class="c">{{ config('pos.thank_you_wa') }}</div>
        @else
            <div class="c">Pay at the counter.</div>
            <div class="c b">M-Pesa Till {{ config('pos.till_number') }}</div>
            <div class="c">(Not a receipt)</div>
        @endif
    </section>
@endif

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
