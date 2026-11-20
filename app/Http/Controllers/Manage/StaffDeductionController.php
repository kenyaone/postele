<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffDeduction;
use Illuminate\Http\Request;

/** Damages / losses charged to staff (recovered in monthly instalments), and payroll details. */
class StaffDeductionController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        $d = $request->validate([
            'incurred_on'  => 'required|date|before_or_equal:today',
            'reason'       => 'required|string|max:150',
            'total'        => 'required|numeric|min:1|max:1000000',
            'per_month'    => 'required|numeric|min:1|max:1000000',
            'start_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $d['per_month'] = min((float) $d['per_month'], (float) $d['total']);
        $staff->deductions()->create($d + ['recorded_by' => $request->user()->id]);
        return back()->with('ok', 'Deduction of KSh ' . number_format((float) $d['total']) . " recorded for {$staff->name}.");
    }

    public function destroy(StaffDeduction $deduction)
    {
        $deduction->delete();
        return back()->with('ok', 'Deduction removed.');
    }

    public function details(Request $request, Staff $staff)
    {
        $d = $request->validate([
            'user_id'   => 'nullable|integer|exists:users,id',
            'id_number' => 'nullable|string|max:30',
            'pay_basis' => 'required|in:monthly,waiter',
        ]);
        $staff->forceFill($d)->save();
        return back()->with('ok', 'Payroll details saved.');
    }
}
