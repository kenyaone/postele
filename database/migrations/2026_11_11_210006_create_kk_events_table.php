<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kk_events', function (Blueprint $table) {
            $table->id();
            $table->json('raw_payload');
            $table->string('event_type');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('reference')->nullable()->index();
            $table->timestamp('received_at');
            $table->enum('status', ['pending', 'matched', 'ignored'])->default('pending')->index();
            $table->timestamps();
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kk_events');
    }
};
