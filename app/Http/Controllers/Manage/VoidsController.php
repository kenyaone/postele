<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Cancelled orders: who, when, why, who approved, what and how much. */
class VoidsController extends Controller
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
        $days = in_array((int) $request->query('days'), [1, 7, 30], true) ? (int) $request->query('days') : 1;
        $from = $date->copy()->subDays($days - 1);

        $rows = Ticket::with(['items.item', 'opener', 'voider', 'approver'])->where('status', 'void')
            ->whereBetween('closed_at', [$from->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderByDesc('closed_at')->get()
            ->map(function ($t) {
                $t->wasSent = $t->items->contains(fn ($l) => $l->sent_at !== null);
                return $t;
            });
        $after = $rows->where('wasSent', true);

        return view('manage.voids', [
            'rows'  => $rows,
            'date'  => $date,
            'from'  => $from,
            'days'  => $days,
            'stats' => (object) [
                'count' => $rows->count(), 'value' => $rows->sum(fn ($t) => $t->total),
                'afterCount' => $after->count(), 'afterValue' => $after->sum(fn ($t) => $t->total),
            ],
        ]);
    }
}
