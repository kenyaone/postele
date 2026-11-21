<?php

namespace App\Services;

use App\Models\DayClose;
use App\Models\Payment;
use App\Models\Ticket;
use Carbon\Carbon;

class DayCloseService
{
    /** Close-outs are required from this day on. */
    public const START = '2026-11-21';

    public const REASONS = [
        'pay_later'     => 'Customer will pay later',
        'mpesa_unknown' => 'Paid by M-Pesa, code not found yet',
        'walked_out'    => 'Customer left without paying',
        'other'         => 'Other (explain)',
    ];

    /** Earliest past day (from START) this waiter worked and has not closed, or null. */
    public function pendingDay(int $userId): ?string
    {
        $worked = Ticket::where('opened_by', $userId)->where('status', '!=', 'void')
            ->where('opened_at', '>=', self::START)->where('opened_at', '<', today())
            ->selectRaw('DATE(opened_at) d')->distinct()->pluck('d')->map(fn ($d) => (string) $d);
        $closed = DayClose::where('user_id', $userId)->pluck('day')->map(fn ($d) => Carbon::parse($d)->toDateString());
        return $worked->diff($closed)->sort()->first();
    }

    /** Figures for one waiter's day. */
    public function summary(int $userId, Carbon $day): array
    {
        $from = $day->copy()->startOfDay(); $to = $day->copy()->endOfDay();
        $bills = Ticket::with('items')->where('opened_by', $userId)->where('status', '!=', 'void')->whereBetween('opened_at', [$from, $to])->orderBy('opened_at')->get();
        $pays = Payment::whereHas('ticket', fn ($q) => $q->where('opened_by', $userId))->whereBetween('received_at', [$from, $to])->get();
        $unpaid = $bills->filter(fn ($t) => in_array($t->status, ['open', 'sent'], true) && (float) $t->balance > 0.004)->values();
        $older = Ticket::where('opened_by', $userId)->whereIn('status', ['open', 'sent'])->where('opened_at', '<', $from)->get()->filter(fn ($t) => (float) $t->balance > 0.004);
        return [
            'bills' => $bills, 'sales' => round((float) $bills->sum('total'), 2),
            'cash' => round((float) $pays->where('method', 'cash')->sum('amount'), 2),
            'mpesa' => round((float) $pays->filter(fn ($p) => str_starts_with((string) $p->method, 'kk_'))->sum('amount'), 2),
            'unpaid' => $unpaid, 'unpaidTotal' => round((float) $unpaid->sum('balance'), 2),
            'olderCount' => $older->count(), 'olderTotal' => round((float) $older->sum('balance'), 2),
        ];
    }
}
