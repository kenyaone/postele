<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YieldRuleItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['yield_rule_id', 'item_id', 'units'];

    protected $casts = ['units' => 'decimal:2'];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
