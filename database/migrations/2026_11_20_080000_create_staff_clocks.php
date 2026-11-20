<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->string('clock_pin_hash')->nullable()->after('pay_basis');
        });
        Schema::create('staff_clocks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('staff_id');
            $t->string('kind', 3); // in | out
            $t->dateTime('at');
            $t->string('photo')->nullable(); // path on the local (private) disk
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['staff_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_clocks');
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn('clock_pin_hash'));
    }
};
