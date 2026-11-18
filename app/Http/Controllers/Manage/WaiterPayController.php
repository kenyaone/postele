<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/** Manage > Settings > Waiter pay: the figures used by App\Services\WaiterPay. */
class WaiterPayController extends Controller
{
    public function update(Request $request)
    {
        $money = ['required', 'numeric', 'min:0', 'max:1000000'];
        $data = $request->validate([
            'pay_daily' => $money,
            'pay_threshold' => $money,
            'pay_threshold_sat' => $money,
            'pay_comm_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'pay_comm_from' => $money,
            'pay_comm_from_sat' => $money,
            'pay_gate' => $money,
            'pay_gate_sat' => $money,
            'pay_start' => ['required', 'date_format:Y-m-d'],
        ], [], [
            'pay_daily' => 'daily pay', 'pay_threshold' => 'daily-pay target', 'pay_threshold_sat' => 'Saturday daily-pay target',
            'pay_comm_pct' => 'commission %', 'pay_comm_from' => 'commission start', 'pay_comm_from_sat' => 'Saturday commission start',
            'pay_gate' => 'cafe gate', 'pay_gate_sat' => 'Saturday cafe gate', 'pay_start' => 'start date',
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return redirect()->route('manage.settings')->with('ok', 'Waiter pay saved.');
    }
}
