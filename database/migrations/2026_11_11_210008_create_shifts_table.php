<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cash_opening', 10, 2);
            $table->decimal('cash_declared', 10, 2)->nullable();
            $table->string('z_report_path')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
