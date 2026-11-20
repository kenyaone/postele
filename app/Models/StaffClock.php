<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo clock-in or clock-out taken on the POS screen. */
class StaffClock extends Model
{
    protected $fillable = ['staff_id', 'kind', 'at', 'photo', 'ip'];
    protected $casts = ['at' => 'datetime'];

    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
}
