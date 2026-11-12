<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stk_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->decimal('amount', 10, 2);
            $table->string('location')->nullable();
            $table->string('kk_id', 64)->nullable()->index();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('error', 300)->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stk_requests');
    }
};
