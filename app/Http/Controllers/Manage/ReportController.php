<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $fromDt = $from->copy()->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        // Sales = money received (payments) in the range
        $byMethod = Payment::whereBetween('received_at', [$fromDt, $toDt])
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as n')->groupBy('method')->get()->keyBy('method');
        $onCredit = (float) ($byMethod['credit']->total ?? 0);   // put on customers' accounts: not money yet
        $sales = (float) $byMethod->sum('total') - $onCredit;

        $tickets = Ticket::cleared()->whereBetween('closed_at', [$fromDt, $toDt])->count();
        $voids   = Ticket::where('status', 'void')->whereBetween('closed_at', [$fromDt, $toDt])->count();

        $expByCat = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->get();
        $expenses = (float) $expByCat->sum('total');
        $purchases = (float) \App\Models\Purchase::whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])->sum('total');
        $payroll   = (float) \App\Models\StaffPayment::whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->sum('amount');

        // Daily table
        $salesDay = Payment::whereBetween('received_at', [$fromDt, $toDt])->where('method', '!=', 'credit')
            ->selectRaw('DATE(received_at) as d, SUM(amount) as s')->groupBy('d')->pluck('s', 'd');
        $tixDay = Ticket::cleared()->whereBetween('closed_at', [$fromDt, $toDt])
            ->selectRaw('DATE(closed_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $expDay = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('spent_on as d, SUM(amount) as s')->groupBy('spent_on')->pluck('s', 'd');
        $purDay = \App\Models\Purchase::whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('purchased_on as d, SUM(total) as s')->groupBy('purchased_on')->pluck('s', 'd');
        $payDay = \App\Models\StaffPayment::whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('paid_on as d, SUM(amount) as s')->groupBy('paid_on')->pluck('s', 'd');
        $daily = [];
        for ($d = $to->copy(); $d->gte($from); $d->subDay()) {
            $k = $d->toDateString();
            $s = (float) ($salesDay[$k] ?? 0); $x = (float) ($expDay[$k] ?? 0); $pu = (float) ($purDay[$k] ?? 0); $pa = (float) ($payDay[$k] ?? 0);
            if ($s == 0 && $x == 0 && $pu == 0 && $pa == 0 && empty($tixDay[$k])) continue;
            $daily[] = ['date' => $d->copy(), 'tickets' => (int) ($tixDay[$k] ?? 0), 'sales' => $s, 'expenses' => $x, 'purchases' => $pu, 'payroll' => $pa, 'net' => $s - $x - $pu - $pa];
        }

        // What sold (paid tickets closed in range)
        $lines = TicketItem::join('tickets', 'ticket_items.ticket_id', '=', 'tickets.id')
            ->join('items', 'ticket_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('tickets.status', 'paid')->whereBetween('tickets.closed_at', [$fromDt, $toDt]);
        $byItem = (clone $lines)->selectRaw('items.name, categories.name as cat, SUM(ticket_items.qty) as qty, SUM(ticket_items.qty * ticket_items.unit_price) as total')
            ->groupBy('items.id', 'items.name', 'categories.name')->orderByDesc('total')->get();
        $byCategory = $byItem->groupBy('cat')->map(fn ($g) => ['qty' => (int) $g->sum('qty'), 'total' => (float) $g->sum('total')])->sortByDesc('total');

        $byCashier = Ticket::join('users', 'tickets.opened_by', '=', 'users.id')
            ->join('payments', 'payments.ticket_id', '=', 'tickets.id')
            ->where('tickets.status', 'paid')->where('payments.method', '!=', 'credit')->whereBetween('tickets.closed_at', [$fromDt, $toDt])
            ->selectRaw('users.name, COUNT(DISTINCT tickets.id) as tickets, SUM(payments.amount) as total')
            ->groupBy('users.id', 'users.name')->orderByDesc('total')->get();

        if ($request->query('export') === 'daily') {
            return $this->csv("tele-cafe-daily-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Date', 'Tickets', 'Sales', 'Expenses', 'Purchases', 'Payroll', 'Net'],
                array_map(fn ($r) => [$r['date']->toDateString(), $r['tickets'], $r['sales'], $r['expenses'], $r['purchases'], $r['payroll'], $r['net']], array_reverse($daily)));
        }
        if ($request->query('export') === 'items') {
            return $this->csv("tele-cafe-items-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Item', 'Category', 'Qty', 'Sales'],
                $byItem->map(fn ($r) => [$r->name, $r->cat, (int) $r->qty, (float) $r->total])->all());
        }

        return view('manage.reports', compact(
            'from', 'to', 'preset', 'sales', 'byMethod', 'tickets', 'voids', 'expenses', 'expByCat', 'purchases', 'payroll',
            'daily', 'byItem', 'byCategory', 'byCashier', 'onCredit'
        ));
    }

    /** Waiter performance: one row per waiter for the chosen dates. */
    public function staff(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $fromDt = $from->copy()->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        $tickets = Ticket::whereBetween('created_at', [$fromDt, $toDt])->get(['id', 'opened_by', 'status', 'opened_at', 'closed_at', 'created_at']);
        $ids     = $tickets->pluck('id');
        $pays    = Payment::whereIn('ticket_id', $ids)->get(['ticket_id', 'method', 'amount'])->groupBy('ticket_id');
        $credits = \Illuminate\Support\Facades\DB::table('credit_sales')->whereIn('ticket_id', $ids)->pluck('amount', 'ticket_id');
        $shifts  = \App\Models\Shift::whereNotNull('closed_at')->whereBetween('closed_at', [$fromDt, $toDt])->get();
        $sr      = app(\App\Services\ShiftReports::class);
        $userIds = $tickets->pluck('opened_by')->merge($shifts->pluck('user_id'))->filter()->unique();
        $names   = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        $rows = [];
        foreach ($userIds as $uid) {
            $mine = $tickets->where('opened_by', $uid);
            $paid = $mine->where('status', 'paid')->reject(fn ($t) => isset($credits[$t->id]));
            $r = ['name' => $names[$uid] ?? ('#' . $uid), 'bills' => $mine->count(), 'paid' => $paid->count(),
                  'cash' => 0.0, 'mpesa' => 0.0, 'other' => 0.0, 'credit' => 0.0, 'mins' => [],
                  'voids' => $mine->where('status', 'void')->count(), 'open' => $mine->where('status', 'open')->count(), 'variance' => null];
            foreach ($mine as $t) {
                foreach ($pays[$t->id] ?? [] as $p) {
                    if ($p->method === 'credit') continue;   // counted below from credit_sales
                    $k = $p->method === 'cash' ? 'cash' : (str_starts_with((string) $p->method, 'kk') ? 'mpesa' : 'other');
                    $r[$k] += (float) $p->amount;
                }
                $r['credit'] += (float) ($credits[$t->id] ?? 0);
                if ($t->status === 'paid' && $t->closed_at && ! isset($credits[$t->id])) {
                    $start = Carbon::parse($t->opened_at ?? $t->created_at);
                    $r['mins'][] = abs($start->diffInSeconds(Carbon::parse($t->closed_at))) / 60;
                }
            }
            foreach ($shifts->where('user_id', $uid) as $s) {
                $exp = (float) ($sr->compute($s)['cashExpected'] ?? 0);
                $r['variance'] = ($r['variance'] ?? 0) + (float) $s->cash_declared - $exp;
            }
            $r['sales']    = $r['cash'] + $r['mpesa'] + $r['other'] + $r['credit'];
            $r['avg']      = $r['paid'] ? ($r['cash'] + $r['mpesa'] + $r['other']) / $r['paid'] : 0;
            $r['voidPct']  = $r['bills'] ? round(100 * $r['voids'] / $r['bills']) : 0;
            $r['avgMins']  = $r['mins'] ? round(array_sum($r['mins']) / count($r['mins'])) : null;
            unset($r['mins']);
            $rows[] = $r;
        }
        usort($rows, fn ($a, $b) => $b['sales'] <=> $a['sales']);

        if ($request->query('export') === 'csv') {
            return $this->csv("tele-cafe-waiters-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Waiter', 'Bills', 'Paid bills', 'Sales', 'Average bill', 'Cash', 'M-Pesa', 'Other', 'Credit', 'Voids', 'Void %', 'Still open', 'Avg minutes to pay', 'Cash short(-)/over(+)'],
                array_map(fn ($r) => [$r['name'], $r['bills'], $r['paid'], $r['sales'], round($r['avg']), $r['cash'], $r['mpesa'], $r['other'], $r['credit'], $r['voids'], $r['voidPct'], $r['open'], $r['avgMins'], $r['variance']], $rows));
        }

        return view('manage.staff-performance', compact('rows', 'from', 'to', 'preset'));
    }

    private function range(Request $request): array
    {
        $today = now()->startOfDay();
        $presets = [
            'today'      => [$today->copy(), $today->copy()],
            'yesterday'  => [$today->copy()->subDay(), $today->copy()->subDay()],
            'this_week'  => [$today->copy()->startOfWeek(), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
        ];
        $p = $request->query('preset');
        if (isset($presets[$p])) return [...$presets[$p], $p];

        try {
            $from = $request->filled('from') ? Carbon::createFromFormat('Y-m-d', $request->query('from'))->startOfDay() : $presets['this_month'][0];
            $to   = $request->filled('to')   ? Carbon::createFromFormat('Y-m-d', $request->query('to'))->startOfDay()   : $today->copy();
        } catch (\Throwable $e) {
            [$from, $to] = $presets['this_month'];
        }
        if ($to->gt($today)) $to = $today->copy();
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        if ($from->diffInDays($to) > 366) $from = $to->copy()->subDays(366);

        return [$from, $to, $request->filled('from') ? null : 'this_month'];
    }

    private function csv(string $name, array $head, array $rows)
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
