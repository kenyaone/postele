<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->after('id');   // POS login, for waiters
            $t->string('id_number', 30)->nullable()->after('phone');
            $t->string('pay_basis', 10)->default('monthly')->after('position'); // monthly | waiter
        });

        Schema::create('staff_attendance', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('staff_id');
            $t->date('day');
            $t->string('status', 10); // present | absent | off | leave | sick
            $t->string('note', 120)->nullable();
            $t->unsignedBigInteger('marked_by')->nullable();
            $t->timestamps();
            $t->unique(['staff_id', 'day']);
        });

        Schema::create('staff_deductions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('staff_id');
            $t->date('incurred_on');
            $t->string('reason', 150);
            $t->decimal('total', 10, 2);
            $t->decimal('per_month', 10, 2);
            $t->string('start_period', 7); // YYYY-MM of the first instalment
            $t->unsignedBigInteger('recorded_by')->nullable();
            $t->timestamps();
            $t->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deductions');
        Schema::dropIfExists('staff_attendance');
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn(['user_id', 'id_number', 'pay_basis']));
    }
};
