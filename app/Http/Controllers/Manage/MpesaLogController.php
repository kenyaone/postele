<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Every M-Pesa payment Kopo Kopo reported: payer name, phone, code, amount, and the bill it cleared. */
class MpesaLogController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        try {
            $date = $request->filled('date') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay() : $today;
        } catch (\Throwable $e) {
            $date = $today;
        }
        if ($date->gt($today)) {
            $date = $today;
        }
        $q = trim((string) $request->query('q', ''));
        $show = in_array($request->query('show'), ['linked', 'unlinked'], true) ? $request->query('show') : 'all';

        $events = KkEvent::whereNotNull('amount')->orderByDesc('received_at')->orderByDesc('id');
        if ($q !== '') {
            $events->where('received_at', '>=', now()->subDays(90));
        } else {
            $events->whereBetween('received_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        }
        $events = $events->limit(1000)->get();
        // One row per M-Pesa code: a phone prompt is reported twice (prompt result + till notification).
        $events = $events->unique(fn ($e) => $e->reference ?: 'id' . $e->id)->values();

        $refs = $events->pluck('reference')->filter()->unique()->values();
        $payments = Payment::with('ticket.opener')->whereIn('kk_reference', $refs)->get()->keyBy('kk_reference');

        $rows = $events->map(function ($e) use ($payments) {
            $p = $e->reference ? $payments->get($e->reference) : null;
            return (object) [
                'event'   => $e,
                'time'    => $e->received_at,
                'name'    => $e->payerName(),
                'phone'   => $e->payerPhone(),
                'code'    => $e->reference,
                'amount'  => (float) $e->amount,
                'payment' => $p,
                'ticket'  => $p?->ticket,
                'status'  => $p ? 'linked' : ($e->status === 'ignored' ? 'ignored' : 'unlinked'),
            ];
        });

        if ($q !== '') {
            $needle = mb_strtolower(preg_replace('/\s+/', ' ', $q));
            $digits = preg_replace('/\D/', '', $q);
            $rows = $rows->filter(function ($r) use ($needle, $digits) {
                if (str_contains(mb_strtolower((string) $r->code), $needle) || str_contains(mb_strtolower($r->name), $needle)) {
                    return true;
                }
                return strlen($digits) >= 3 && str_contains(preg_replace('/\D/', '', (string) $r->phone), $digits);
            })->values();
        }
        $all = $rows;
        if ($show !== 'all') {
            $rows = $rows->filter(fn ($r) => $show === 'linked' ? $r->status === 'linked' : $r->status !== 'linked')->values();
        }

        return view('manage.mpesa', [
            'rows'  => $rows,
            'date'  => $date,
            'q'     => $q,
            'show'  => $show,
            'stats' => (object) [
                'count'    => $all->count(),
                'total'    => $all->sum('amount'),
                'linked'   => $all->where('status', 'linked')->sum('amount'),
                'unlinked' => $all->where('status', '!=', 'linked')->sum('amount'),
                'unlinkedCount' => $all->where('status', '!=', 'linked')->count(),
            ],
        ]);
    }
}
