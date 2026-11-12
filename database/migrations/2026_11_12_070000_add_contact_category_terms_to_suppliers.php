<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact', 60)->nullable()->after('name');
            $table->string('category', 40)->nullable()->after('phone');
            $table->string('terms', 30)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact', 'category', 'terms']);
        });
    }
};
