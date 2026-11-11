<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('printer', ['kitchen', 'cashier']);
            $table->binary('payload_bytes');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->unsignedInteger('tries')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
