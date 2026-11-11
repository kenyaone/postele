<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['cash', 'kk_stk', 'kk_till_auto', 'kk_till_manual']);
            $table->decimal('amount', 10, 2);
            $table->string('kk_reference')->nullable()->index();
            $table->timestamp('received_at');
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['ticket_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
