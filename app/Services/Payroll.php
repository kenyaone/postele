<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly payroll: what each person earned, less absences and damage instalments, less what was paid.
 * Waiters (pay_basis = waiter) earn the POS daily pay + commission; everyone else a monthly salary,
 * less monthly_salary / 30 for each day marked "absent" (off days, leave and sick are not deducted).
 */
class Payroll
{
    public const STATUSES = ['present' => 'Present', 'absent' => 'Absent', 'off' => 'Off day', 'leave' => 'Leave', 'sick' => 'Sick'];

    private ?array $earned = null;
    private ?string $earnedFor = null;

    /** [first moment, last moment] of a YYYY-MM period, capped at now. */
    public function range(string $period): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfDay();
        $end = $from->copy()->endOfMonth();
        return [$from, $end->gt(now()) ? now()->endOfDay() : $end];
    }

    /** user_id => KSh earned by each waiter in the period (daily pay + commission). */
    public function waiterEarnings(string $period): array
    {
        if ($this->earnedFor === $period) { return $this->earned; }
        [$from, $to] = $this->range($period);
        $out = [];
        if ($from->lte($to)) {
            foreach (app(WaiterPay::class)->days($from, $to) as $r) {
                $out[$r['user_id']] = ($out[$r['user_id']] ?? 0) + (float) ($r['total'] ?? 0);
            }
        }
        $this->earnedFor = $period;
        return $this->earned = $out;
    }

    public function row(Staff $s, string $period): object
    {
        [$from, $to] = $this->range($period);
        $absent = StaffAttendance::where('staff_id', $s->id)->where('status', 'absent')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->count();
        $waiter = $s->pay_basis === 'waiter';
        $base = $waiter ? round($this->waiterEarnings($period)[$s->user_id] ?? 0, 2) : (float) $s->monthly_salary;
        $dayRate = $waiter ? 0.0 : round((float) $s->monthly_salary / 30, 2);
        $absenceDeduction = round($absent * $dayRate, 2);
        $damages = round($s->deductions->sum(fn ($d) => $d->instalmentFor($period)), 2);
        $sums = $s->payments()->where('period', $period)->selectRaw('type, SUM(amount) t')->groupBy('type')->pluck('t', 'type');
        $salary = (float) ($sums['salary'] ?? 0);
        $advance = (float) ($sums['advance'] ?? 0);
        $due = round($base - $absenceDeduction - $damages, 2);

        return (object) [
            'staff' => $s, 'base' => $base, 'absent' => $absent, 'dayRate' => $dayRate,
            'absenceDeduction' => $absenceDeduction, 'damages' => $damages, 'due' => $due,
            'salary' => $salary, 'advance' => $advance, 'bonus' => (float) ($sums['bonus'] ?? 0),
            'paid' => $salary + $advance, 'balance' => round($due - $salary - $advance, 2),
        ];
    }

    public function forPeriod(string $period): Collection
    {
        return Staff::with('deductions')->orderByDesc('active')->orderBy('name')->get()
            ->filter(fn ($s) => $s->active || $s->payments()->where('period', $period)->exists())
            ->map(fn ($s) => $this->row($s, $period))->values();
    }
}
