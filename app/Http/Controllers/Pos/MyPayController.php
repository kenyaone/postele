<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Services\WaiterPay;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** "My pay": a waiter's own collections, daily pay and commission (today, this month, last month). */
class MyPayController extends Controller
{
    public function show(Request $request, WaiterPay $pay)
    {
        $user = $request->user();
        if ($user->role !== 'waiter') {
            return view('pos.my-pay', ['isWaiter' => false]);
        }

        $today = now()->startOfDay();
        $last = $today->copy()->subMonthNoOverflow();
        $periods = [
            [$today->copy()->startOfMonth(), $today->copy(), 'This month (' . $today->format('F') . ')'],
            [$last->copy()->startOfMonth(), $last->copy()->endOfMonth()->startOfDay(), 'Last month (' . $last->format('F') . ')'],
        ];

        $months = [];
        foreach ($periods as [$from, $to, $label]) {
            $days = collect($pay->days($from, $to))->where('user_id', $user->id)->sortByDesc('date')->values()
                ->map(function ($d) use ($pay) {
                    $r = $pay->rules(Carbon::parse($d['date']));
                    return $d + ['gate' => $r['gate'], 'from' => $r['from'], 'active' => $r['active'], 'gateMet' => $d['cafe'] + 0.004 >= $r['gate']];
                });
            $months[] = [
                'label' => $label,
                'days' => $days->all(),
                'collected' => round($days->sum('collected'), 2),
                'daily' => round($days->sum('daily'), 2),
                'commission' => round($days->sum('commission'), 2),
                'total' => round($days->sum('total'), 2),
            ];
        }

        return view('pos.my-pay', ['isWaiter' => true, 'today' => $pay->today($user->id), 'months' => $months]);
    }
}
