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

    /** How this payment was confirmed: [label, tone ok|warn|plain, detail]. */
    public function verification(): array
    {
        if ($this->method === 'cash') {
            return ['Cash', 'plain', null];
        }
        if ($this->method === 'credit') {
            return ['On account', 'plain', null];
        }
        $event = $this->kk_reference ? KkEvent::where('reference', $this->kk_reference)->latest('id')->first() : null;
        if ($event) {
            $detail = 'Kopo Kopo received KSh ' . number_format((float) $event->amount, 2)
                . ($event->received_at ? ' on ' . $event->received_at->format('d M H:i') : '');
            if ((float) $event->amount + 0.001 < (float) $this->amount) {
                return ['Amount differs', 'warn', $detail];
            }
            return ['Verified by Kopo Kopo', 'ok', $detail];
        }
        if (in_array($this->method, ['kk_stk', 'kk_till_auto'], true)) {
            return ['Confirmed by Kopo Kopo', 'ok', null];
        }
        return ['Not yet seen by Kopo Kopo', 'warn', 'Code typed by the cashier; no matching Kopo Kopo notification yet.'];
    }
}
