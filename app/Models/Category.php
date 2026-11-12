<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'channel', 'sort_order', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
