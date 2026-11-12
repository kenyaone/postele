<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = ['printer','printer_id','ticket_id','kind','payload_bytes','status','tries','sent_at','error'];

    protected $casts = [
        'tries'   => 'integer',
        'sent_at' => 'datetime',
    ];

    public function target() { return $this->belongsTo(Printer::class, 'printer_id'); }
}
