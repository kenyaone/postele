<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 30)->nullable();
            $table->string('position', 60)->nullable();
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('paid_on');
            $table->char('period', 7);   // YYYY-MM the payment counts towards
            $table->enum('type', ['salary', 'advance', 'bonus'])->default('salary');
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'mpesa', 'bank', 'other'])->default('cash');
            $table->string('reference', 40)->nullable();
            $table->string('notes', 160)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index(['period', 'staff_id']);
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_payments');
        Schema::dropIfExists('staff');
    }
};
