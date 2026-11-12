<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'pin_hash',
        'active',
        'whatsapp_phone',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function canManage(): bool
    {
        return $this->role === 'manager';
    }

    public function canCashier(): bool
    {
        return in_array($this->role, ['cashier', 'manager'], true);
    }

    public function canWait(): bool
    {
        return in_array($this->role, ['waiter', 'cashier', 'manager'], true);
    }
}
