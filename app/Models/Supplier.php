<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'notes', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
