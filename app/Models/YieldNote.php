<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YieldNote extends Model
{
    protected $fillable = ['stock_item_id', 'day', 'reason', 'explained_by'];

    protected $casts = ['day' => 'date'];

    public function explainer(): BelongsTo { return $this->belongsTo(User::class, 'explained_by'); }
}
