<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPayment extends Model
{
    protected $fillable = ['customer_id', 'amount', 'fees', 'method', 'reference', 'note', 'received_at', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'fees' => 'decimal:2', 'received_at' => 'datetime'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
