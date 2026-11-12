<?php

namespace App\Services;

use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Ticket;
use App\Models\TicketItem;

class ShiftReports
{
    public function compute(Shift $shift): array
    {
        $start = $shift->opened_at;
        $end   = $shift->closed_at ?? now();

        $payments = Payment::whereBetween('received_at', [$start, $end])->get();

        $byMethod = [
            'cash'            => 0.0,
            'kk_stk'          => 0.0,
            'kk_till_auto'    => 0.0,
            'kk_till_manual'  => 0.0,
        ];
        foreach ($payments as $p) {
            $byMethod[$p->method] = ($byMethod[$p->method] ?? 0) + (float) $p->amount;
        }
        $cashSales  = $byMethod['cash'];
        $totalSales = array_sum($byMethod);

        $ticketsPaid = Ticket::where('status', 'paid')
            ->whereBetween('closed_at', [$start, $end])->count();
        $ticketsVoid = Ticket::where('status', 'void')
            ->whereBetween('closed_at', [$start, $end])->count();

        $itemRows = TicketItem::join('tickets', 'ticket_items.ticket_id', '=', 'tickets.id')
            ->join('items', 'ticket_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('tickets.status', 'paid')
            ->whereBetween('tickets.closed_at', [$start, $end])
            ->selectRaw('categories.name as cat_name, items.name as item_name,
                SUM(ticket_items.qty) as qty,
                SUM(ticket_items.qty * ticket_items.unit_price) as total')
            ->groupBy('categories.id', 'categories.name', 'items.id', 'items.name')
            ->orderBy('categories.name')
            ->orderBy('items.name')
            ->get();

        $byCategory = [];
        $byItem     = [];
        foreach ($itemRows as $r) {
            $byCategory[$r->cat_name] = ($byCategory[$r->cat_name] ?? 0) + (float) $r->total;
            $byItem[] = (object) [
                'name'  => $r->item_name,
                'cat'   => $r->cat_name,
                'qty'   => (int) $r->qty,
                'total' => (float) $r->total,
            ];
        }

        $cashExpected = (float) $shift->cash_opening + $cashSales;
        $cashDeclared = $shift->cash_declared !== null ? (float) $shift->cash_declared : null;
        $variance     = $cashDeclared !== null ? $cashDeclared - $cashExpected : null;

        $kkUnmatched = KkEvent::where('status', 'pending')->count();

        return compact(
            'byMethod', 'cashSales', 'totalSales',
            'ticketsPaid', 'ticketsVoid',
            'byCategory', 'byItem',
            'cashExpected', 'cashDeclared', 'variance',
            'kkUnmatched'
        );
    }
}
