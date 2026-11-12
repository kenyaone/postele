<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['ticket_id','method','amount','kk_reference','received_at','matched_at','matched_by'];

    protected $casts = [
        'amount'      => 'decimal:2',
        'received_at' => 'datetime',
        'matched_at'  => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
