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

    /**
     * Names that are not a real person or office: "Table", "Table 4", "Customer", "Walk-in", "Guest", "Meza"...
     * Credit is only for someone who can be chased for the money later.
     */
    public static function isPlaceholderName(?string $name): bool
    {
        $n = mb_strtolower(trim((string) $name));
        $n = preg_replace('/[^a-z ]+/u', ' ', $n);           // drop digits and punctuation: "Table 4" -> "table"
        $n = trim(preg_replace('/\s+/', ' ', $n));
        if (mb_strlen(str_replace(' ', '', $n)) < 3) return true;
        $words = ['table', 'tables', 'tbl', 'meza', 'customer', 'customers', 'mteja', 'wateja', 'client', 'walk in', 'walkin',
                  'walk', 'guest', 'guests', 'cash', 'mpesa', 'm pesa', 'pesa', 'test', 'testing', 'unknown', 'none', 'name',
                  'counter', 'takeaway', 'take away', 'credit', 'debt', 'deni', 'house', 'dine in', 'person', 'someone', 'mtu',
                  'visitor', 'student', 'staff', 'waiter', 'later', 'pay later', 'tab'];
        return in_array($n, $words, true);
    }
}
