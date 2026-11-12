<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->enum('role', ['bill', 'kot', 'both'])->default('both');
            $t->enum('connection', ['network', 'device'])->default('network');
            $t->string('ip', 45)->nullable();
            $t->unsignedSmallInteger('port')->default(9100);
            $t->json('stations')->nullable();          // KOT routing: null = every dish
            $t->boolean('auto_receipt')->default(false); // print a receipt by itself after payment
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('print_jobs', function (Blueprint $t) {
            $t->foreignId('printer_id')->nullable()->after('printer')->constrained('printers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', fn (Blueprint $t) => $t->dropConstrainedForeignId('printer_id'));
        Schema::dropIfExists('printers');
    }
};
