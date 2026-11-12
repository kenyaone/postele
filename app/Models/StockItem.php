<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    public const UNITS = ['kg', 'g', 'L', 'ml', 'pcs', 'trays', 'bags', 'crates', 'packets', 'bunches', 'cylinders', 'bottles'];

    protected $fillable = ['name', 'unit', 'reorder_level', 'par_level', 'active'];

    protected $casts = [
        'reorder_level' => 'decimal:2',
        'par_level'     => 'decimal:2',
        'active'        => 'boolean',
    ];

    public function counts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }
}
