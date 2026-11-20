<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Ticket;
use App\Services\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Daily attendance register, marked by a manager for every active staff member (waiters and kitchen). */
class StaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $day = $this->day($request->query('date'));
        $marks = StaffAttendance::whereDate('day', $day)->get()->keyBy('staff_id');
        $worked = Ticket::whereDate('opened_at', $day)->where('status', '!=', 'void')
            ->selectRaw('opened_by, COUNT(*) n')->groupBy('opened_by')->pluck('n', 'opened_by');
        $month = StaffAttendance::whereBetween('day', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])
            ->get()->groupBy('staff_id')->map(fn ($g) => $g->countBy('status'));

        return view('manage.hr.attendance', [
            'day'      => $day,
            'staff'    => Staff::where('active', true)->orderBy('name')->get(),
            'marks'    => $marks,
            'worked'   => $worked,
            'saved'    => $marks->isNotEmpty(),
            'statuses' => Payroll::STATUSES,
            'month'    => $month,
            'clocks'   => \App\Models\StaffClock::whereDate('at', $day)->orderBy('at')->get()->groupBy('staff_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'day'      => 'required|date|before_or_equal:today',
            'status'   => 'required|array',
            'status.*' => 'in:' . implode(',', array_keys(Payroll::STATUSES)),
            'note'     => 'nullable|array',
            'note.*'   => 'nullable|string|max:120',
        ]);
        $day = Carbon::parse($data['day'])->toDateString();
        $ids = Staff::whereIn('id', array_keys($data['status']))->pluck('id')->all();
        foreach ($data['status'] as $staffId => $status) {
            if (! in_array((int) $staffId, $ids, true)) { continue; }
            StaffAttendance::updateOrCreate(
                ['staff_id' => (int) $staffId, 'day' => $day],
                ['status' => $status, 'note' => $data['note'][$staffId] ?? null, 'marked_by' => $request->user()->id]
            );
        }
        return redirect()->route('manage.hr.attendance', ['date' => $day])
            ->with('ok', 'Attendance saved for ' . Carbon::parse($day)->format('D j M') . '.');
    }

    private function day(?string $d): Carbon
    {
        try { $c = $d ? Carbon::parse($d)->startOfDay() : today(); } catch (\Throwable) { $c = today(); }
        return $c->gt(today()) ? today() : $c;
    }
}
