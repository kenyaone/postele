<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 2);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('void_at')->nullable();
            $table->timestamps();
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
    }
};
