<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayment extends Model
{
    public const TYPES = ['salary' => 'Salary', 'advance' => 'Advance', 'bonus' => 'Bonus / extra'];

    protected $fillable = ['staff_id', 'paid_on', 'period', 'type', 'amount', 'method', 'reference', 'notes', 'recorded_by'];
    protected $casts = ['paid_on' => 'date', 'amount' => 'decimal:2'];

    public function staff(): BelongsTo    { return $this->belongsTo(Staff::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
