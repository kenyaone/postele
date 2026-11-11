<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Manager', 'email' => 'manager@example.com', 'role' => 'manager', 'pin' => '9999'],
            ['name' => 'Cashier', 'email' => 'cashier@example.com', 'role' => 'cashier', 'pin' => '1234'],
            ['name' => 'Waiter',  'email' => 'waiter@example.com',  'role' => 'waiter',  'pin' => '1111'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'     => $u['name'],
                    'password' => Hash::make(Str::random(32)),
                    'role'     => $u['role'],
                    'pin_hash' => Hash::make($u['pin']),
                    'active'   => true,
                ]
            );
        }
    }
}
