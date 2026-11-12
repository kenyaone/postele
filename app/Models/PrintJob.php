<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = ['printer','payload_bytes','status','tries','sent_at','error'];

    protected $casts = [
        'tries'   => 'integer',
        'sent_at' => 'datetime',
    ];
}
