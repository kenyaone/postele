<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\Shift;
use App\Services\Receipts;
use App\Services\ShiftReports;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function openForm(Request $request)
    {
        $existing = Shift::currentFor($request->user()->id);
        if ($existing) return redirect()->route('pos.shift.show');
        return view('shift.open');
    }

    public function open(Request $request)
    {
        $existing = Shift::currentFor($request->user()->id);
        if ($existing) return redirect()->route('pos.shift.show');

        $data = $request->validate([
            'cash_opening' => 'required|numeric|min:0|max:1000000',
        ]);

        Shift::create([
            'user_id'      => $request->user()->id,
            'opened_at'    => now(),
            'cash_opening' => $data['cash_opening'],
        ]);

        return redirect()->route('pos.index')->with('ok', 'Shift opened.');
    }

    public function show(Request $request, ShiftReports $reports)
    {
        $shift = Shift::currentFor($request->user()->id)
            ?? Shift::where('user_id', $request->user()->id)->latest('opened_at')->first();

        if (! $shift) return redirect()->route('pos.shift.openForm');

        $shift->load('user');
        $report = $reports->compute($shift);
        return view('shift.show', compact('shift', 'report'));
    }

    public function close(Request $request, ShiftReports $reports, Receipts $receipts)
    {
        $shift = Shift::currentFor($request->user()->id);
        if (! $shift) {
            return redirect()->route('pos.shift.openForm')->withErrors(['shift' => 'No open shift to close.']);
        }

        $data = $request->validate([
            'cash_declared' => 'required|numeric|min:0|max:1000000',
        ]);

        $shift->cash_declared = $data['cash_declared'];
        $shift->closed_at     = now();
        $shift->save();

        $shift->load('user');
        $report  = $reports->compute($shift);
        $payload = $receipts->buildShiftReport($shift, $report, isZ: true);

        app(\App\Services\PrintRouter::class)->cashier($payload, false, null, 'report');

        return redirect()->route('pos.shift.show')->with('ok', 'Shift closed. Z-report queued to cashier printer.');
    }
}
