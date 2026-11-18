<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Keys a manager may edit; each maps to config('pos.<key>'). */
    public const EDITABLE = ['cafe_name', 'till_number', 'phone', 'thank_you_wa', 'address', 'print_logo'];

    /** Waiter pay figures (App\Services\WaiterPay), saved from Manage > Settings > Waiter pay. */
    public const PAY = ['pay_daily', 'pay_threshold', 'pay_threshold_sat', 'pay_comm_pct', 'pay_comm_from', 'pay_comm_from_sat', 'pay_gate', 'pay_gate_sat', 'pay_start'];

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Copy saved values over the .env defaults in config('pos.*'). */
    public static function applyToConfig(): void
    {
        $saved = self::whereIn('key', array_merge(self::EDITABLE, ['logo'], self::PAY))->pluck('value', 'key');
        foreach ($saved as $key => $value) {
            if ($value !== null && $value !== '') {
                config(['pos.' . $key => $value]);
            }
        }
    }
}
