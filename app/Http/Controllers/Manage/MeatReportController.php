<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\MeatCheck;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeatReportController extends Controller
{
    public function index(Request $request, MeatCheck $check)
    {
        try { $to = Carbon::parse($request->query('to', today()->toDateString()))->startOfDay(); } catch (\Throwable) { $to = today(); }
        if ($to->gt(today())) { $to = today(); }
        $from = $to->copy()->subDays(6);
        $rows = $check->range($from, $to);
        $totals = [];
        foreach (array_keys(MeatCheck::MEATS) as $m) {
            $days = collect($rows)->pluck($m)->filter(fn ($r) => $r->used !== null);
            $used = $days->sum('used'); $plates = $days->sum('plates');
            $totals[$m] = (object) ['used' => $used, 'plates' => $plates, 'perKg' => $used > 0 ? round($plates / $used, 1) : null,
                'missing' => $days->sum('missing'), 'missingCost' => $days->sum('missingCost'), 'days' => $days->count()];
        }
        return view('manage.meat', ['rows' => $rows, 'totals' => $totals, 'from' => $from, 'to' => $to, 'meats' => MeatCheck::MEATS, 'min' => MeatCheck::MIN_PER_KG]);
    }
}
