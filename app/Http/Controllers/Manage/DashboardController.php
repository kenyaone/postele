<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Shift;
use App\Models\Ticket;
use App\Models\TicketItem;

class DashboardController extends Controller
{
    public function index()
    {
        $today      = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $salesToday   = (float) Payment::where('received_at', '>=', $today)->sum('amount');
        $byMethod     = Payment::where('received_at', '>=', $today)
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method');
        $ticketsToday = Ticket::where('status', 'paid')->where('closed_at', '>=', $today)->count();
        $voidsToday   = Ticket::where('status', 'void')->where('closed_at', '>=', $today)->count();
        $expToday     = (float) Expense::whereDate('spent_on', $today->toDateString())->sum('amount');

        $salesMonth = (float) Payment::where('received_at', '>=', $monthStart)->sum('amount');
        $expMonth   = (float) Expense::where('spent_on', '>=', $monthStart->toDateString())->sum('amount');

        // Last 7 days, oldest first
        $since = now()->subDays(6)->startOfDay();
        $salesByDay = Payment::where('received_at', '>=', $since)
            ->selectRaw('DATE(received_at) as d, SUM(amount) as s')->groupBy('d')->pluck('s', 'd');
        $expByDay = Expense::where('spent_on', '>=', $since->toDateString())
            ->selectRaw('spent_on as d, SUM(amount) as s')->groupBy('spent_on')->pluck('s', 'd');
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $key = $d->toDateString();
            $days[] = [
                'label'    => $i === 0 ? 'Today' : $d->format('D j'),
                'sales'    => (float) ($salesByDay[$key] ?? 0),
                'expenses' => (float) ($expByDay[$key] ?? 0),
            ];
        }
        $chartMax = max(1, max(array_map(fn ($d) => max($d['sales'], $d['expenses']), $days)));

        $topItems = TicketItem::join('tickets', 'ticket_items.ticket_id', '=', 'tickets.id')
            ->join('items', 'ticket_items.item_id', '=', 'items.id')
            ->where('tickets.status', 'paid')->where('tickets.closed_at', '>=', $today)
            ->selectRaw('items.name, SUM(ticket_items.qty) as qty, SUM(ticket_items.qty * ticket_items.unit_price) as total')
            ->groupBy('items.id', 'items.name')->orderByDesc('qty')->limit(5)->get();

        return view('manage.index', [
            'salesToday'   => $salesToday,
            'byMethod'     => $byMethod,
            'ticketsToday' => $ticketsToday,
            'voidsToday'   => $voidsToday,
            'avgTicket'    => $ticketsToday ? $salesToday / $ticketsToday : 0,
            'expToday'     => $expToday,
            'salesMonth'   => $salesMonth,
            'expMonth'     => $expMonth,
            'days'         => $days,
            'chartMax'     => $chartMax,
            'topItems'     => $topItems,
            'openTickets'  => Ticket::whereIn('status', ['open', 'sent'])->count(),
            'openShifts'   => Shift::with('user')->whereNull('closed_at')->get(),
            'printPending' => PrintJob::where('status', 'pending')->count(),
            'printFailed'  => PrintJob::where('status', 'failed')->count(),
        ]);
    }
}
