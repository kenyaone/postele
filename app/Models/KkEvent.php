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
}
