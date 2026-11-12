<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditSale extends Model
{
    protected $fillable = ['ticket_id', 'customer_id', 'amount', 'due_on', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'due_on' => 'date'];

    public function ticket(): BelongsTo   { return $this->belongsTo(Ticket::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
