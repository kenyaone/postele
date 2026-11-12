<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORIES = [
        'Food supplies', 'Gas / charcoal', 'Rent', 'Electricity', 'Water', 'Wages / casuals',
        'Transport', 'Packaging', 'Cleaning supplies', 'Repairs', 'Internet / airtime',
        'Licences & permits', 'Marketing', 'Bank / M-Pesa charges', 'Other',
    ];

    public const METHODS = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank', 'other' => 'Other'];

    protected $fillable = ['spent_on', 'category', 'description', 'amount', 'method', 'reference', 'recorded_by'];

    protected $casts = [
        'spent_on' => 'date',
        'amount'   => 'decimal:2',
    ];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
