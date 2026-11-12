<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** Every order ever taken, with filters. Waiters see their own orders; cashiers and managers see all. */
class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $day = function ($v, $fallback) { try { return $v ? Carbon::createFromFormat('Y-m-d', (string) $v)->startOfDay() : $fallback; } catch (\Throwable $e) { return $fallback; } };
        $to = $day($request->query('to'), now()->startOfDay());
        if ($to->gt(now())) { $to = now()->startOfDay(); }
        $from = $day($request->query('from'), $to->copy()->subDays(6));
        if ($from->gt($to)) { $from = $to->copy(); }
        $pick = fn ($k, $allowed) => in_array($request->query($k), $allowed, true) ? $request->query($k) : 'all';
        $status = $pick('status', ['paid', 'open', 'sent', 'void']);
        $type   = $pick('type', ['house', 'glovo']);
        $method = $pick('method', ['cash', 'mpesa', 'credit']);
        $q = trim((string) $request->query('q', ''));
        $seeAll = in_array($user->role, ['manager', 'cashier'], true);
        $waiter = $seeAll ? (int) $request->query('waiter', 0) : $user->id;

        $base = Ticket::query();
        if ($q !== '' && preg_match('/^#?\d{1,2}[-\/. ]\d{1,2}[-\/. ]\d{2,4}[-\/. ]\d{1,4}$/', $q)) {
            $base->where('id', optional(\App\Models\Ticket::findByOrderNo($q))->id ?? 0);
        } elseif ($q !== '' && ctype_digit(ltrim($q, '#0') ?: '0') && ltrim($q, '#0') !== '') {
            $base->where('id', (int) ltrim($q, '#0'));
        } elseif ($q !== '') {
            $base->whereHas('payments', fn ($p) => $p->where('kk_reference', 'like', '%' . strtoupper($q) . '%'));
        } else {
            $base->whereBetween('opened_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        }
        if ($status !== 'all') { $base->where('status', $status); }
        if ($type !== 'all' && Schema::hasColumn('tickets', 'channel')) { $base->where('channel', $type); }
        if ($method === 'cash')   { $base->whereHas('payments', fn ($p) => $p->where('method', 'cash')); }
        if ($method === 'mpesa')  { $base->whereHas('payments', fn ($p) => $p->whereIn('method', ['kk_stk', 'kk_till_auto', 'kk_till_manual'])); }
        if ($method === 'credit') { $base->whereHas('payments', fn ($p) => $p->where('method', 'credit')); }
        if ($waiter) { $base->where('opened_by', $waiter); }

        $byStatus = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $sales = (float) Payment::whereIn('ticket_id', (clone $base)->where('status', 'paid')->select('id'))->sum('amount');
        $tickets = (clone $base)->with(['opener', 'payments', 'items'])->orderByDesc('id')->paginate(50)->withQueryString();

        return view('pos.history', [
            'tickets' => $tickets, 'byStatus' => $byStatus, 'sales' => $sales,
            'from' => $from, 'to' => $to, 'status' => $status, 'type' => $type, 'method' => $method, 'q' => $q,
            'waiter' => $waiter, 'seeAll' => $seeAll,
            'staff' => $seeAll ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'hasChannel' => Schema::hasColumn('tickets', 'channel'),
        ]);
    }
}
