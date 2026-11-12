<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = ['user_id','opened_at','closed_at','cash_opening','cash_declared','z_report_path'];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'cash_opening'  => 'decimal:2',
        'cash_declared' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public static function currentFor(int $userId): ?self
    {
        return self::where('user_id', $userId)->whereNull('closed_at')->latest('opened_at')->first();
    }
}
