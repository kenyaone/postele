<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['cashier', 'waiter', 'manager', 'stock'])->default('cashier')->change();
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('unit', 20);
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->decimal('par_level', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->date('counted_on');
            $table->decimal('quantity', 10, 2);
            $table->foreignId('counted_by')->constrained('users');
            $table->timestamps();
            $table->unique(['stock_item_id', 'counted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_items');
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['cashier', 'waiter', 'manager'])->default('cashier')->change();
        });
    }
};
