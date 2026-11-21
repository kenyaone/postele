<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\DayClose;
use App\Services\DayCloseService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** "End my day": a waiter counts the cash they hand over and explains every bill still unpaid. */
class DayEndController extends Controller
{
    public function show(Request $request, DayCloseService $svc)
    {
        $user = $request->user();
        $day = $this->day($request->query('day') ?? $svc->pendingDay($user->id));
        $done = DayClose::where('user_id', $user->id)->whereDate('day', $day)->first();
        return view('pos.day-end', ['day' => $day, 'done' => $done, 's' => $svc->summary($user->id, $day),
            'reasons' => DayCloseService::REASONS, 'pending' => $svc->pendingDay($user->id)]);
    }

    public function store(Request $request, DayCloseService $svc)
    {
        $user = $request->user();
        $data = $request->validate([
            'day'            => 'required|date|before_or_equal:today',
            'cash_declared'  => 'required|numeric|min:0|max:1000000',
            'reason'         => 'nullable|array',
            'reason.*'       => 'in:' . implode(',', array_keys(DayCloseService::REASONS)),
            'why'            => 'nullable|array',
            'why.*'          => 'nullable|string|max:120',
            'note'           => 'nullable|string|max:200',
        ]);
        $day = $this->day($data['day']);
        if (DayClose::where('user_id', $user->id)->whereDate('day', $day)->exists()) {
            return redirect()->route('pos.dayend', ['day' => $day->toDateString()])->withErrors(['dayend' => 'This day is already closed.']);
        }
        $s = $svc->summary($user->id, $day);
        $explain = [];
        foreach ($s['unpaid'] as $t) {
            $r = $data['reason'][$t->id] ?? null;
            $w = trim((string) ($data['why'][$t->id] ?? ''));
            if (! $r || ($r === 'other' && $w === '')) {
                return back()->withInput()->withErrors(['dayend' => 'Say why bill #' . $t->order_no . ' is still unpaid (or clear it first).']);
            }
            $explain[$t->id] = ['bill' => $t->order_no, 'amount' => (float) $t->balance, 'reason' => $r, 'note' => $w];
        }
        DayClose::create([
            'user_id' => $user->id, 'day' => $day->toDateString(), 'bills' => $s['bills']->count(), 'sales' => $s['sales'],
            'cash_expected' => $s['cash'], 'cash_declared' => round((float) $data['cash_declared'], 2), 'mpesa' => $s['mpesa'],
            'unpaid_count' => $s['unpaid']->count(), 'unpaid_amount' => $s['unpaidTotal'], 'explanations' => $explain,
            'note' => $data['note'] ?? null, 'closed_at' => now(),
        ]);
        $gap = round($s['cash'] - (float) $data['cash_declared'], 2);
        $msg = 'Day closed for ' . $day->format('D j M') . '. ' . ($gap > 0.004 ? 'Cash SHORT by KSh ' . number_format($gap) . '.' : ($gap < -0.004 ? 'Cash over by KSh ' . number_format(-$gap) . '.' : 'Cash matches.'));
        return redirect()->route('pos.dayend', ['day' => $day->toDateString()])->with('ok', $msg);
    }

    private function day(?string $d): Carbon
    {
        try { $c = $d ? Carbon::parse($d)->startOfDay() : today(); } catch (\Throwable) { $c = today(); }
        return $c->gt(today()) ? today() : $c;
    }
}
