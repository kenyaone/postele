<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StkRequest extends Model
{
    protected $fillable = ['ticket_id', 'phone', 'amount', 'location', 'kk_id', 'status', 'error', 'requested_by'];
    protected $casts = ['amount' => 'decimal:2'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
}
