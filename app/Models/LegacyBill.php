<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyBill extends Model
{
    protected $fillable = ['source', 'bill_date', 'bill_no', 'bill_time', 'waiter', 'items', 'amount', 'status', 'kk_reference', 'verified', 'note', 'cleared_by', 'cleared_at'];

    protected $casts = ['bill_date' => 'date', 'amount' => 'decimal:2', 'verified' => 'boolean', 'cleared_at' => 'datetime'];

    public const LABELS = [
        'unpaid'      => ['NOT PAID', 'bg-red-100 text-red-800'],
        'mpesa'       => ['PAID · M-Pesa', 'bg-emerald-100 text-emerald-800'],
        'cash'        => ['PAID · Cash', 'bg-emerald-100 text-emerald-800'],
        'written_off' => ['Written off', 'bg-gray-200 text-gray-700'],
    ];

    public function clearer() { return $this->belongsTo(User::class, 'cleared_by'); }
}
