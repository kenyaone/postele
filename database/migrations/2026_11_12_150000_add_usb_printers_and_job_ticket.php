<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE printers MODIFY connection ENUM('network','device','usb') NOT NULL DEFAULT 'network'");
        Schema::table('printers', function (Blueprint $t) {
            $t->string('windows_name', 120)->nullable()->after('ip'); // USB printer on the bridge PC, as named in Windows
        });
        Schema::table('print_jobs', function (Blueprint $t) {
            $t->foreignId('ticket_id')->nullable()->after('printer_id')->constrained('tickets')->nullOnDelete();
            $t->string('kind', 10)->nullable()->after('ticket_id'); // kot | bill | receipt | test | report
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $t) { $t->dropConstrainedForeignId('ticket_id'); $t->dropColumn('kind'); });
        Schema::table('printers', fn (Blueprint $t) => $t->dropColumn('windows_name'));
        DB::table('printers')->where('connection', 'usb')->update(['connection' => 'device']);
        DB::statement("ALTER TABLE printers MODIFY connection ENUM('network','device') NOT NULL DEFAULT 'network'");
    }
};
