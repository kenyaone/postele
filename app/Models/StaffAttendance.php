<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';
    protected $fillable = ['staff_id', 'day', 'status', 'note', 'marked_by'];
    protected $casts = ['day' => 'date'];

    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
}
