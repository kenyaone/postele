@php
    $k = fn ($n) => 'KSh ' . number_format((float) $n);
    $no = fn ($id) => '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $labels = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'credit' => 'On account (credit)'];
    $td = 'padding:6px 10px;border-bottom:1px solid #eee;font-size:14px;';
    $h = 'margin:22px 0 6px;font-size:15px;color:#2b1b14;';
@endphp
<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto;color:#2b1b14;background:#fff;">
    <div style="background:#2b1b14;color:#f7efe4;padding:18px 20px;border-radius:14px 14px 0 0;">
        <div style="font-size:13px;opacity:.8;">{{ config('pos.cafe_name', 'Tele Cafe') }} &middot; end of day</div>
        <div style="font-size:22px;font-weight:bold;margin-top:2px;">{{ $day->format('l j F Y') }}</div>
    </div>
    <table width="100%" cellspacing="0" cellpadding="0" style="background:#f7efe4;border-radius:0 0 14px 14px;"><tr>
        <td style="padding:16px 20px;"><div style="font-size:12px;color:#6b3f26;">SALES</div><div style="font-size:26px;font-weight:bold;">{{ $k($sales) }}</div></td>
        <td style="padding:16px 20px;"><div style="font-size:12px;color:#6b3f26;">ORDERS PAID</div><div style="font-size:26px;font-weight:bold;">{{ $orders }}</div></td>
        <td style="padding:16px 20px;"><div style="font-size:12px;color:#6b3f26;">CASH IN DRAWER</div><div style="font-size:26px;font-weight:bold;">{{ $k($cashIn) }}</div></td>
    </tr></table>

    @if ($unlinked->count() || $openBills->count() || $voids->count())
        <div style="margin-top:16px;padding:12px 16px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;font-size:14px;">
            <b>Check these:</b>
            @if ($unlinked->count())<div>&#9888; {{ $unlinked->count() }} M-Pesa payment(s) not on any bill: {{ $k($unlinked->sum('amount')) }}</div>@endif
            @if ($openBills->count())<div>&#9888; {{ $openBills->count() }} bill(s) still unpaid: {{ $k($openBills->sum(fn ($t) => $t->total)) }}</div>@endif
            @if ($voids->count())<div>&#9888; {{ $voids->count() }} order(s) cancelled: {{ $k($voids->sum(fn ($t) => $t->total)) }}</div>@endif
        </div>
    @else
        <div style="margin-top:16px;padding:12px 16px;border-radius:12px;background:#ecfdf5;border:1px solid #a7f3d0;font-size:14px;">&#10003; All M-Pesa payments are on bills, no unpaid bills, no cancellations.</div>
    @endif

    <h3 style="{{ $h }}">How customers paid</h3>
    <table width="100%" cellspacing="0">
        @foreach ($labels as $key => $label)
            <tr><td style="{{ $td }}">{{ $label }}</td><td style="{{ $td }}text-align:right;font-weight:bold;">{{ $k($byMethod[$key] ?? 0) }}</td></tr>
        @endforeach
        @foreach ($byChannel as $ch => $amt)
            <tr><td style="{{ $td }}color:#6b3f26;">{{ $ch }}</td><td style="{{ $td }}text-align:right;color:#6b3f26;">{{ $k($amt) }}</td></tr>
        @endforeach
    </table>

    @if ($credit['given'] || $credit['repaid'] || $credit['owed'])
        <h3 style="{{ $h }}">Credit &amp; debts</h3>
        <table width="100%" cellspacing="0">
            <tr><td style="{{ $td }}">Put on account today</td><td style="{{ $td }}text-align:right;">{{ $k($credit['given']) }}</td></tr>
            <tr><td style="{{ $td }}">Repaid today</td><td style="{{ $td }}text-align:right;">{{ $k($credit['repaid']) }}{{ $credit['repaid_cash'] ? ' (' . $k($credit['repaid_cash']) . ' cash)' : '' }}</td></tr>
            <tr><td style="{{ $td }}font-weight:bold;">Total still owed to the cafe</td><td style="{{ $td }}text-align:right;font-weight:bold;">{{ $k($credit['owed']) }}</td></tr>
        </table>
    @endif

    @if ($top->count())
        <h3 style="{{ $h }}">Best sellers</h3>
        <table width="100%" cellspacing="0">
            @foreach ($top as $t)
                <tr><td style="{{ $td }}">{{ \Illuminate\Support\Str::after($t->name, 'Glovo: ') }}</td><td style="{{ $td }}text-align:right;">{{ (int) $t->qty }}</td><td style="{{ $td }}text-align:right;">{{ $k($t->value) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($staff->count())
        <h3 style="{{ $h }}">By staff</h3>
        <table width="100%" cellspacing="0">
            @foreach ($staff as $name => $s)
                <tr><td style="{{ $td }}">{{ $name }}</td><td style="{{ $td }}text-align:right;">{{ $s['orders'] }} orders</td><td style="{{ $td }}text-align:right;">{{ $k($s['sales']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($unlinked->count())
        <h3 style="{{ $h }}">M-Pesa not on any bill</h3>
        <table width="100%" cellspacing="0">
            @foreach ($unlinked as $e)
                <tr><td style="{{ $td }}">{{ $e->created_at->format('H:i') }}</td><td style="{{ $td }}">{{ $e->payerName() ?: '-' }}</td><td style="{{ $td }}font-family:monospace;">{{ $e->reference }}</td><td style="{{ $td }}text-align:right;">{{ $k($e->amount) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($openBills->count())
        <h3 style="{{ $h }}">Bills still unpaid</h3>
        <table width="100%" cellspacing="0">
            @foreach ($openBills as $t)
                <tr><td style="{{ $td }}">{{ $no($t->id) }}</td><td style="{{ $td }}">{{ $t->created_at->format('D H:i') }}</td><td style="{{ $td }}">{{ $t->opener->name ?? '?' }}</td><td style="{{ $td }}text-align:right;">{{ $k($t->total) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($voids->count())
        <h3 style="{{ $h }}">Cancelled orders</h3>
        <table width="100%" cellspacing="0">
            @foreach ($voids as $t)
                <tr><td style="{{ $td }}">{{ $no($t->id) }}</td><td style="{{ $td }}">{{ $t->opener->name ?? '?' }}</td><td style="{{ $td }}">{{ $t->void_reason ?? '' }}</td><td style="{{ $td }}text-align:right;">{{ $k($t->total) }}</td></tr>
            @endforeach
        </table>
    @endif

    <p style="margin-top:24px;font-size:12px;color:#888;text-align:center;"><a href="{{ url('/manage') }}" style="color:#6b3f26;">Open the back office</a> &middot; Tele Cafe POS &middot; Developed by MTTI Eldoret</p>
</div>
