@php
    $__pa = false; $__seen = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('printers') && \App\Models\Printer::where('active', true)->whereIn('connection', ['network', 'usb'])->exists()) {
            $__seen = \Illuminate\Support\Facades\Cache::get('print_bridge_seen');
            $__pa = ! $__seen || now()->timestamp - (int) $__seen > 120;
        }
    } catch (\Throwable $e) {}
@endphp
@if ($__pa)
    <div class="bg-red-600 text-white text-sm font-semibold px-4 py-2 text-center print:hidden">&#9888; Printer offline{{ $__seen ? ' since ' . \Illuminate\Support\Carbon::createFromTimestamp($__seen)->setTimezone(config('app.timezone'))->format('D H:i') : '' }}: kitchen tickets and bills are not printing. Switch on the counter PC and start the print bridge.</div>
@endif

@php
    $__mp = false;
    try { $__mp = \App\Models\StkRequest::where('status', 'pending')->where('created_at', '<=', now()->subMinutes(5))->where('created_at', '>=', now()->subHours(3))->exists(); } catch (\Throwable $e) {}
@endphp
@if ($__mp)
    <div class="bg-amber-400 text-stone-900 text-sm font-semibold px-4 py-2 text-center print:hidden">&#9888; An M-Pesa prompt has had no answer from Kopo Kopo for over 5 minutes. If the customer paid, find their payment on the M-Pesa tab by code.</div>
@endif
