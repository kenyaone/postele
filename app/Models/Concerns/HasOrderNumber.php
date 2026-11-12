<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** Orders are numbered per day: 12-11-2026-007 = the 7th order on 12 Nov 2026. */
trait HasOrderNumber
{
    private static ?bool $hasDailyNo = null;

    protected static function bootHasOrderNumber(): void
    {
        static::creating(function ($t) {
            if (! (self::$hasDailyNo ??= Schema::hasColumn('tickets', 'daily_no'))) return;
            $day = $t->created_at ? Carbon::parse($t->created_at) : now();
            $t->daily_no = (int) static::whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->max('daily_no') + 1;
        });
    }

    public function getOrderNoAttribute(): string
    {
        $d = $this->created_at ?? now();
        return $this->daily_no ? $d->format('d-m-Y') . '-' . str_pad((string) $this->daily_no, 3, '0', STR_PAD_LEFT)
                               : str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** "12-11-2026-007" (also 12/11/2026/7, 12-11-26-7), "7" = today's 7th, "00045" = old style. */
    public static function findByOrderNo(string $q): ?self
    {
        $q = trim(ltrim(trim($q), '#'));
        if (preg_match('/^(\d{1,2})[-\/. ](\d{1,2})[-\/. ](\d{2,4})[-\/. ](\d{1,4})$/', $q, $m)) {
            $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if (! checkdate((int) $m[2], (int) $m[1], $y)) return null;
            $day = Carbon::create($y, (int) $m[2], (int) $m[1]);
            return static::whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->where('daily_no', (int) $m[4])->first();
        }
        if (ctype_digit($q) && strlen($q) <= 3) {
            return static::whereBetween('created_at', [today(), today()->endOfDay()])->where('daily_no', (int) $q)->first();
        }
        return ctype_digit($q) ? static::find((int) $q) : null;
    }

    public static function orderNoFor($id): string
    {
        return optional(static::find($id))->order_no ?? (string) $id;
    }
}
