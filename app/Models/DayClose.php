<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A waiter's end-of-day close-out: cash counted against cash recorded, and reasons for unpaid bills. */
class DayClose extends Model
{
    protected $fillable = ['user_id', 'day', 'bills', 'sales', 'cash_expected', 'cash_declared', 'mpesa', 'unpaid_count', 'unpaid_amount', 'explanations', 'note', 'closed_at'];
    protected $casts = ['day' => 'date', 'closed_at' => 'datetime', 'explanations' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shortfall(): float { return round((float) $this->cash_expected - (float) $this->cash_declared, 2); }
}
