<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Keys a manager may edit; each maps to config('pos.<key>'). */
    public const EDITABLE = ['cafe_name', 'till_number', 'phone', 'thank_you_wa', 'address', 'print_logo'];

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Copy saved values over the .env defaults in config('pos.*'). */
    public static function applyToConfig(): void
    {
        $saved = self::whereIn('key', array_merge(self::EDITABLE, ['logo']))->pluck('value', 'key');
        foreach ($saved as $key => $value) {
            if ($value !== null && $value !== '') {
                config(['pos.' . $key => $value]);
            }
        }
    }
}
