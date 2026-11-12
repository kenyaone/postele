<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->unsignedSmallInteger('daily_no')->nullable()->after('id'); // 7 in 12-11-2026-007
            $t->index(['created_at', 'daily_no']);
        });
        // Number every existing order by its day, oldest first.
        $n = []; 
        foreach (DB::table('tickets')->orderBy('id')->get(['id', 'created_at']) as $r) {
            $d = substr((string) $r->created_at, 0, 10);
            $n[$d] = ($n[$d] ?? 0) + 1;
            DB::table('tickets')->where('id', $r->id)->update(['daily_no' => $n[$d]]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $t) { $t->dropIndex(['created_at', 'daily_no']); $t->dropColumn('daily_no'); });
    }
};
