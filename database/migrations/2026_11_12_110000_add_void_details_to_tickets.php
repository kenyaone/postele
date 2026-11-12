<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cancelled orders: why, who cancelled, and which manager approved (needed once the kitchen has the order).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('void_reason', 255)->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('void_approved_by')->nullable()->after('voided_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_approved_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('void_reason');
        });
    }
};
