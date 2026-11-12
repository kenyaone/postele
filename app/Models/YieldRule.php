<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YieldRule extends Model
{
    protected $fillable = ['stock_item_id', 'basis', 'raw_qty', 'output', 'all_items', 'tolerance_pct', 'active'];

    protected $casts = [
        'raw_qty'       => 'decimal:2',
        'output'        => 'decimal:2',
        'all_items'     => 'boolean',
        'tolerance_pct' => 'integer',
        'active'        => 'boolean',
    ];

    public function stockItem(): BelongsTo { return $this->belongsTo(StockItem::class); }
    public function lines(): HasMany       { return $this->hasMany(YieldRuleItem::class); }
}
