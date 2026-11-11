<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->enum('station', ['kitchen', 'cashier'])->default('kitchen');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
