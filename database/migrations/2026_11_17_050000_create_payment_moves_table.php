<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Log of M-Pesa payments moved from one bill to another (who, when, why). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->index();
            $table->string('kk_reference', 40)->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('from_ticket_id')->nullable()->index();
            $table->unsignedBigInteger('to_ticket_id')->index();
            $table->string('reason', 150);
            $table->unsignedBigInteger('moved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_moves');
    }
};
