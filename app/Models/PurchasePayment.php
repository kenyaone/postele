<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    protected $fillable = ['purchase_id', 'paid_on', 'amount', 'method', 'reference', 'recorded_by'];

    protected $casts = [
        'paid_on' => 'date',
        'amount'  => 'decimal:2',
    ];

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
