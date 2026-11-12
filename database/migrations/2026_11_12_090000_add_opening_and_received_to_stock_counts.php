<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Daily stock sheet: opening + received (bought today) - closing (quantity) = used.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->decimal('opening', 10, 2)->nullable()->after('counted_on');   // typed only for an item's first day; otherwise the previous closing count
            $table->decimal('received', 10, 2)->default(0)->after('opening');    // bought / received during the day
            $table->decimal('quantity', 10, 2)->nullable()->change();            // closing count; empty until counted at close
        });
    }

    public function down(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropColumn(['opening', 'received']);
        });
    }
};
