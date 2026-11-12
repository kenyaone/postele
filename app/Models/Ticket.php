<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = ['opened_by', 'status', 'opened_at', 'closed_at', 'notes', 'void_reason', 'voided_by', 'void_approved_by'];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function voider(): BelongsTo   { return $this->belongsTo(User::class, 'voided_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'void_approved_by'); }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($i) => (float) $i->unit_price * (int) $i->qty);
    }
}
