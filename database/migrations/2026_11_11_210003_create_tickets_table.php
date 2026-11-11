<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->constrained('users');
            $table->enum('status', ['open', 'sent', 'paid', 'void'])->default('open');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
