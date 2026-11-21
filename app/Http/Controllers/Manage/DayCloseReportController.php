<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\DayClose;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DayCloseService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Waiters' end-of-day close-outs: cash recorded vs handed over, unpaid bills explained, days not closed. */
class DayCloseReportController extends Controller
{
    public function index(Request $request)
    {
        $from = max(Carbon::parse(DayCloseService::START), today()->subDays(13));
        $closes = DayClose::with('user:id,name')->whereDate('day', '>=', $from)->orderByDesc('day')->orderBy('user_id')->get();
        $closed = $closes->map(fn ($c) => $c->user_id . '|' . $c->day->toDateString())->flip();
        $missing = Ticket::where('status', '!=', 'void')->where('opened_at', '>=', $from)->where('opened_at', '<', today())
            ->whereIn('opened_by', User::where('role', 'waiter')->pluck('id'))
            ->selectRaw('opened_by, DATE(opened_at) d, COUNT(*) n')->groupBy('opened_by', 'd')->get()
            ->reject(fn ($r) => isset($closed[$r->opened_by . '|' . $r->d]));
        return view('manage.day-closes', ['closes' => $closes, 'missing' => $missing, 'users' => User::pluck('name', 'id'), 'reasons' => DayCloseService::REASONS]);
    }
}
