<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Unpaid bills carried over from the old DineHQ POS, to be cleared (M-Pesa, cash or written off). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_bills', function (Blueprint $t) {
            $t->id();
            $t->string('source', 20)->default('dinehq');
            $t->date('bill_date');
            $t->string('bill_no', 10);
            $t->string('bill_time', 5)->nullable();
            $t->string('waiter', 60);
            $t->text('items')->nullable();
            $t->decimal('amount', 10, 2);
            $t->string('status', 20)->default('unpaid');   // unpaid | mpesa | cash | written_off
            $t->string('kk_reference', 20)->nullable()->index();
            $t->boolean('verified')->default(false);       // M-Pesa code found in Kopo Kopo records
            $t->string('note', 255)->nullable();
            $t->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cleared_at')->nullable();
            $t->timestamps();
            $t->unique(['source', 'bill_date', 'bill_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_bills');
    }
};
