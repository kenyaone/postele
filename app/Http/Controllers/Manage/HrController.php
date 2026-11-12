<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\StaffPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrController extends Controller
{
    public function index(Request $request)
    {
        $period = $this->period($request->query('month'));
        $from   = Carbon::createFromFormat('Y-m-d', $period . '-01');

        $sums = StaffPayment::where('period', $period)
            ->selectRaw('staff_id, type, SUM(amount) as total')->groupBy('staff_id', 'type')->get()
            ->groupBy('staff_id')->map(fn ($g) => $g->pluck('total', 'type'));

        $rows = Staff::orderByDesc('active')->orderBy('name')->get()
            ->filter(fn ($s) => $s->active || isset($sums[$s->id]))
            ->map(function ($s) use ($sums) {
                $t = $sums[$s->id] ?? collect();
                $salary = (float) ($t['salary'] ?? 0); $advance = (float) ($t['advance'] ?? 0); $bonus = (float) ($t['bonus'] ?? 0);
                return (object) [
                    'staff' => $s, 'due' => (float) $s->monthly_salary, 'salary' => $salary, 'advance' => $advance, 'bonus' => $bonus,
                    'balance' => round((float) $s->monthly_salary - $salary - $advance, 2),
                ];
            })->values();

        return view('manage.hr.index', [
            'period'     => $period,
            'label'      => $from->format('F Y'),
            'prev'       => $from->copy()->subMonth()->format('Y-m'),
            'next'       => $from->copy()->addMonth()->format('Y-m'),
            'rows'       => $rows,
            'inactive'   => Staff::where('active', false)->orderBy('name')->get(),
        ]);
    }

    public function storeStaff(Request $request)
    {
        $s = Staff::create($this->staffData($request) + ['active' => true]);
        return redirect()->route('manage.hr.staff', $s)->with('ok', "{$s->name} added to staff.");
    }

    public function showStaff(Request $request, Staff $staff)
    {
        $period = $this->period($request->query('month'));
        return view('manage.hr.staff', [
            'staff'    => $staff,
            'period'   => $period,
            'balance'  => $staff->balanceFor($period),
            'payments' => $staff->payments()->with('recorder')->orderByDesc('paid_on')->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function updateStaff(Request $request, Staff $staff)
    {
        $data = $this->staffData($request);
        $data['active'] = $request->boolean('active');
        $staff->update($data);
        return redirect()->route('manage.hr.staff', $staff)->with('ok', 'Staff details saved.');
    }

    public function storePayment(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'period'    => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'type'      => 'required|in:' . implode(',', array_keys(StaffPayment::TYPES)),
            'amount'    => 'required|numeric|min:0.01|max:10000000',
            'paid_on'   => 'required|date|before_or_equal:today',
            'method'    => 'required|in:' . implode(',', array_keys(Expense::METHODS)),
            'reference' => 'nullable|string|max:40',
            'notes'     => 'nullable|string|max:160',
        ], ['period.regex' => 'Pick the month this payment is for.']);

        $staff->payments()->create($data + ['recorded_by' => $request->user()->id]);
        $left = $staff->balanceFor($data['period']);
        $msg = StaffPayment::TYPES[$data['type']] . ' of KSh ' . number_format($data['amount'], 2) . " recorded for {$staff->name}.";
        if ($data['type'] !== 'bonus') {
            $msg .= $left > 0 ? ' Still owed for that month: KSh ' . number_format($left, 2) . '.' : ($left < 0 ? ' Note: paid KSh ' . number_format(-$left, 2) . ' more than the salary.' : ' Salary for that month is fully paid.');
        }
        return redirect()->route('manage.hr.staff', [$staff, 'month' => $data['period']])->with('ok', $msg);
    }

    public function destroyPayment(StaffPayment $payment)
    {
        $staff = $payment->staff;
        $payment->delete();
        return redirect()->route('manage.hr.staff', $staff)->with('ok', 'Payment removed.');
    }

    private function staffData(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:80',
            'phone'          => 'nullable|string|max:30',
            'position'       => 'nullable|string|max:60',
            'monthly_salary' => 'required|numeric|min:0|max:10000000',
            'start_date'     => 'nullable|date',
        ]);
    }

    private function period(?string $p): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $p) ? $p : now()->format('Y-m');
    }
}
