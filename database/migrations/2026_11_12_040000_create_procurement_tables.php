<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('notes', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->date('purchased_on');
            $table->string('reference', 40)->nullable();
            $table->string('description', 200);
            $table->decimal('total', 12, 2);
            $table->date('due_on')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index('purchased_on');
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'mpesa', 'bank', 'other'])->default('cash');
            $table->string('reference', 40)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
