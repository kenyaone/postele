<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use \App\Models\Concerns\HasOrderNumber;

    protected $fillable = ['opened_by', 'status', 'opened_at', 'closed_at', 'notes', 'void_reason', 'voided_by', 'void_approved_by', 'channel'];

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

    public function creditSale(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(CreditSale::class); }

    /** Really paid: closed with cash or M-Pesa. A bill put on a customer's account is NOT paid. */
    public function scopeCleared($q)
    {
        return $q->where('tickets.status', 'paid')->whereDoesntHave('payments', fn ($p) => $p->where('method', 'credit'));
    }

    /** Closed on a customer's account: money still owed (see Debts). */
    public function scopeOnCredit($q)
    {
        return $q->where('tickets.status', 'paid')->whereHas('payments', fn ($p) => $p->where('method', 'credit'));
    }

    public function isOnCredit(): bool
    {
        return $this->status === 'paid' && $this->payments->contains('method', 'credit');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($i) => (float) $i->unit_price * (int) $i->qty);
    }
}
