<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';
    protected $fillable = ['name', 'phone', 'position', 'monthly_salary', 'start_date', 'active'];
    protected $casts = ['monthly_salary' => 'decimal:2', 'start_date' => 'date', 'active' => 'boolean'];

    public function payments(): HasMany
    {
        return $this->hasMany(StaffPayment::class);
    }

    /** What is still owed in salary for a YYYY-MM period (advances count towards salary; bonuses do not). */
    public function balanceFor(string $period): float
    {
        $paid = (float) $this->payments()->where('period', $period)->whereIn('type', ['salary', 'advance'])->sum('amount');
        return round((float) $this->monthly_salary - $paid, 2);
    }
}
