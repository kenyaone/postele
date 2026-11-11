<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['cashier', 'waiter', 'manager'])->default('cashier')->after('password');
            $table->string('pin_hash', 255)->nullable()->after('role');
            $table->boolean('active')->default(true)->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'pin_hash', 'active']);
        });
    }
};
