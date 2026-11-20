<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Audit trail viewer: who did what and when. */
class ActivityController extends Controller
{
    public function index(Request $request)
    {
        try { $day = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay(); } catch (\Throwable) { $day = today(); }
        $userId = $request->integer('user') ?: null;
        $kind = in_array($request->query('kind'), ['actions', 'pages', 'logins', 'errors'], true) ? $request->query('kind') : 'all';

        $base = ActivityLog::whereBetween('created_at', [$day, $day->copy()->endOfDay()]);
        $summary = (clone $base)->whereNotNull('user_id')
            ->selectRaw("user_id, MIN(created_at) first_at, MAX(created_at) last_at, SUM(method = 'GET') pages, SUM(method <> 'GET') actions, COUNT(DISTINCT ip) devices")
            ->groupBy('user_id')->orderBy('first_at')->get();
        $failed = (clone $base)->where('note', 'like', 'LOGIN FAILED%')->count();

        $q = (clone $base)->with('user:id,name,role')->orderByDesc('id');
        if ($userId) { $q->where('user_id', $userId); }
        match ($kind) {
            'actions' => $q->where('method', '!=', 'GET'),
            'pages'   => $q->where('method', 'GET'),
            'logins'  => $q->whereNotNull('note'),
            'errors'  => $q->where('status', '>=', 400),
            default   => null,
        };

        return view('manage.activity', [
            'day' => $day, 'userId' => $userId, 'kind' => $kind, 'failed' => $failed,
            'summary' => $summary, 'rows' => $q->paginate(100)->withQueryString(),
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** "manage.hr.staff.details" -> "Hr  Staff  Details" */
    public static function label(?string $route, string $path): string
    {
        if (! $route) { return $path; }
        $parts = array_values(array_filter(explode('.', $route), fn ($p) => ! in_array($p, ['manage', 'index'], true)));
        return implode('  ', array_map(fn ($p) => ucfirst(str_replace(['-', '_'], ' ', $p)), $parts)) ?: $path;
    }
}
