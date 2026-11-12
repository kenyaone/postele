<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Yield tracking: raw material (stock item) -> what it should produce, and the kitchen's explanations for variances.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('basis', ['units', 'sales'])->default('units');   // portions made, or KSh of sales
            $table->decimal('raw_qty', 10, 2);                              // e.g. 2 (kg of flour)
            $table->decimal('output', 12, 2);                               // e.g. 30 (chapatis) or 3000 (KSh)
            $table->boolean('all_items')->default(false);                   // sales basis: count all menu sales
            $table->unsignedTinyInteger('tolerance_pct')->default(10);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('yield_rule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yield_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('units', 8, 2)->default(1);                     // portions used per menu item sold
            $table->unique(['yield_rule_id', 'item_id']);
        });
        Schema::create('yield_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('reason', 500);
            $table->foreignId('explained_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['stock_item_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_notes');
        Schema::dropIfExists('yield_rule_items');
        Schema::dropIfExists('yield_rules');
    }
};
