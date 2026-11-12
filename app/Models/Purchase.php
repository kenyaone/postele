<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['supplier_id', 'purchased_on', 'reference', 'description', 'total', 'due_on', 'recorded_by'];

    protected $casts = [
        'purchased_on' => 'date',
        'due_on'       => 'date',
        'total'        => 'decimal:2',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function payments(): HasMany  { return $this->hasMany(PurchasePayment::class); }

    /** Uses withSum('payments as paid', 'amount') when loaded, else queries. */
    public function paidAmount(): float
    {
        return (float) ($this->attributes['paid'] ?? $this->payments()->sum('amount'));
    }

    public function balance(): float
    {
        return round((float) $this->total - $this->paidAmount(), 2);
    }

    public function status(): string
    {
        $b = $this->balance();
        if ($b <= 0.004) return 'paid';
        return $this->paidAmount() > 0 ? 'partial' : 'unpaid';
    }

    public function isOverdue(): bool
    {
        return $this->status() !== 'paid' && $this->due_on && $this->due_on->lt(today());
    }
}
