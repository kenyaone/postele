<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    protected $fillable = ['stock_item_id', 'counted_on', 'quantity', 'counted_by'];

    protected $casts = [
        'counted_on' => 'date',
        'quantity'   => 'decimal:2',
    ];

    public function item(): BelongsTo    { return $this->belongsTo(StockItem::class, 'stock_item_id'); }
    public function counter(): BelongsTo { return $this->belongsTo(User::class, 'counted_by'); }
}
