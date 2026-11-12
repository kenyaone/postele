<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PDO;

class BackupDatabase extends Command
{
    protected $signature = 'pos:backup {--no-mail : Keep the copy on the server only} {--to= : Send to this address instead}';
    /** Throw-away tables: structure only, no data (logins, cache, queues). */
    private const SKIP_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];
    protected $description = 'Back up the POS database (keeps 14 days on the server, emails a copy)';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) mkdir($dir, 0700, true);
        $file = $dir . '/telecafe-pos-' . now()->format('Y-m-d-Hi') . '.sql.gz';
        try {
            $rows = $this->dump($file);
        } catch (\Throwable $e) {
            @unlink($file);
            $this->error('Backup failed: ' . $e->getMessage());
            report($e);
            return self::FAILURE;
        }
        $old = glob($dir . '/telecafe-pos-*.sql.gz');
        rsort($old);
        foreach (array_slice($old, 14) as $f) @unlink($f);
        $size = max(1, round(filesize($file) / 1024)) . ' KB';
        $this->info("Saved $file ($size, $rows rows)");

        $email = $this->option('to') ?: Setting::where('key', 'report_email')->value('value');
        if (! $this->option('no-mail') && $email && filesize($file) < 18 * 1024 * 1024) {
            Mail::raw(
                "Nightly backup of the Tele Cafe POS database ($size, $rows records), " . now()->format('D j M Y H:i') . ".\n\nKeep this email. To restore, give the attached file to whoever manages the server (it imports with phpMyAdmin or mysql). The last 14 copies are also kept on the server.",
                fn ($m) => $m->to($email)->subject('Tele Cafe POS backup ' . now()->format('D j M Y'))->attach($file)
            );
            $this->info("Emailed to $email");
        }
        return self::SUCCESS;
    }

    /** Plain SQL export (no shell needed): structure + data for every table. */
    private function dump(string $file): int
    {
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($file, 'wb9');
        $w = fn (string $s) => gzwrite($gz, $s);
        $w("-- Tele Cafe POS backup " . now()->toDateTimeString() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $total = 0;
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $t) {
            $table = array_values((array) $t)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `$table`"))[1];
            $w("DROP TABLE IF EXISTS `$table`;\n$create;\n");
            if (in_array($table, self::SKIP_DATA, true)) { $w("\n"); continue; }
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : (is_string($v) && ! mb_check_encoding($v, 'UTF-8') ? '0x' . bin2hex($v) : $pdo->quote((string) $v)), $row)) . ')';
                $total++;
                if (count($batch) === 200) { $w("INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
            }
            if ($batch) $w("INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        if (filesize($file) < 200) throw new \RuntimeException('export is empty');
        return $total;
    }
}
