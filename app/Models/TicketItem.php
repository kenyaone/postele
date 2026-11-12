<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketItem extends Model
{
    protected $fillable = ['ticket_id', 'item_id', 'qty', 'unit_price', 'sent_at', 'void_at'];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'decimal:2',
        'sent_at'    => 'datetime',
        'void_at'    => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->unit_price * (int) $this->qty;
    }
}
