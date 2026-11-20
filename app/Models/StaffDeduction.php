<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A damage or loss charged to a staff member, recovered in monthly instalments from start_period. */
class StaffDeduction extends Model
{
    protected $fillable = ['staff_id', 'incurred_on', 'reason', 'total', 'per_month', 'start_period', 'recorded_by'];
    protected $casts = ['incurred_on' => 'date', 'total' => 'decimal:2', 'per_month' => 'decimal:2'];

    public function staff(): BelongsTo    { return $this->belongsTo(Staff::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    private function monthsFromStart(string $period): int
    {
        [$y1, $m1] = array_map('intval', explode('-', $this->start_period));
        [$y2, $m2] = array_map('intval', explode('-', $period));
        return ($y2 - $y1) * 12 + ($m2 - $m1);
    }

    /** Amount taken off pay in the given YYYY-MM period. */
    public function instalmentFor(string $period): float
    {
        $n = $this->monthsFromStart($period);
        if ($n < 0) { return 0.0; }
        $before = min((float) $this->total, (float) $this->per_month * $n);
        return round(max(0, min((float) $this->per_month, (float) $this->total - $before)), 2);
    }

    /** Total recovered up to and including the given period. */
    public function recoveredBy(string $period): float
    {
        $n = $this->monthsFromStart($period);
        return $n < 0 ? 0.0 : round(min((float) $this->total, (float) $this->per_month * ($n + 1)), 2);
    }
}
