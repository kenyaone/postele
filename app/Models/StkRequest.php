<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StkRequest extends Model
{
    protected $fillable = ['ticket_id', 'phone', 'amount', 'location', 'kk_id', 'status', 'error', 'requested_by'];
    protected $casts = ['amount' => 'decimal:2'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }

    /**
     * One prompt can pay several orders: the first row is the prompt itself (amount = what the customer
     * is asked for) and each extra order gets its own row with the same Kopo Kopo id.
     */
    public function group()
    {
        return $this->kk_id ? static::where('kk_id', $this->kk_id)->orderBy('id')->get() : collect([$this]);
    }

    public function isLead(): bool
    {
        return ! $this->kk_id || (int) static::where('kk_id', $this->kk_id)->min('id') === (int) $this->id;
    }
}
