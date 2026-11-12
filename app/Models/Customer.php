<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone who buys on credit (or a delivery platform such as Glovo). */
class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'notes', 'is_platform'];

    protected $casts = ['is_platform' => 'boolean'];

    public function creditSales(): HasMany { return $this->hasMany(CreditSale::class); }
    public function payments(): HasMany    { return $this->hasMany(CreditPayment::class); }

    /** Adds owed_total, paid_total and fees_total for the balance. */
    public function scopeWithTotals($query)
    {
        return $query->withSum('creditSales as owed_total', 'amount')
            ->withSum('payments as paid_total', 'amount')
            ->withSum('payments as fees_total', 'fees');
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->owed_total - (float) $this->paid_total - (float) $this->fees_total, 2);
    }
}
