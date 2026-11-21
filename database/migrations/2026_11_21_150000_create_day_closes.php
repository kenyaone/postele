<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_closes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->date('day');
            $t->unsignedInteger('bills')->default(0);
            $t->decimal('sales', 10, 2)->default(0);
            $t->decimal('cash_expected', 10, 2)->default(0);
            $t->decimal('cash_declared', 10, 2)->default(0);
            $t->decimal('mpesa', 10, 2)->default(0);
            $t->unsignedInteger('unpaid_count')->default(0);
            $t->decimal('unpaid_amount', 10, 2)->default(0);
            $t->json('explanations')->nullable(); // ticket_id => [reason, note]
            $t->string('note', 200)->nullable();
            $t->dateTime('closed_at');
            $t->timestamps();
            $t->unique(['user_id', 'day']);
        });
    }

    public function down(): void { Schema::dropIfExists('day_closes'); }
};
