<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('method', 8);
            $t->string('path', 255);
            $t->string('route', 100)->nullable();
            $t->string('subject', 150)->nullable(); // route parameters, e.g. ticket=523
            $t->unsignedSmallInteger('status');
            $t->string('ip', 45)->nullable();
            $t->string('agent', 150)->nullable();
            $t->string('note', 120)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('created_at');
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('activity_logs'); }
};
