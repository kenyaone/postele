<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KkEvent extends Model
{
    protected $table = 'kk_events';

    protected $fillable = ['raw_payload','event_type','amount','reference','received_at','status'];

    protected $casts = [
        'raw_payload' => 'array',
        'amount'      => 'decimal:2',
        'received_at' => 'datetime',
    ];
    /** Payer's full name as Kopo Kopo sent it. */
    public function payerName(): string
    {
        $r = data_get($this->raw_payload, 'event.resource', []);
        $name = trim(implode(' ', array_filter([data_get($r, 'sender_first_name'), data_get($r, 'sender_middle_name'), data_get($r, 'sender_last_name')])));
        return $name !== '' ? $name : (string) data_get($r, 'sender_name', '');
    }

    /** Payer's phone number as Kopo Kopo sent it (may be partly masked). */
    public function payerPhone(): string
    {
        return (string) data_get($this->raw_payload, 'event.resource.sender_phone_number', '');
    }
}
