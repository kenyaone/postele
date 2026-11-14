<?php

namespace App\Console\Commands;

use App\Models\LegacyBill;
use Illuminate\Console\Command;

/** Import unpaid DineHQ bills from a CSV: bill_no,date(Y-m-d),time,waiter,items,amount. Safe to run twice. */
class ImportLegacyBills extends Command
{
    protected $signature = 'pos:import-legacy {file} {--source=dinehq}';
    protected $description = 'Import unpaid bills from the old POS (CSV) into Old bills';

    public function handle(): int
    {
        $f = fopen($this->argument('file'), 'r');
        if (! $f) { $this->error('Cannot open file'); return 1; }
        $new = 0; $skip = 0; $sum = 0;
        fgetcsv($f);   // header
        while (($r = fgetcsv($f)) !== false) {
            if (count($r) < 6) continue;
            [$no, $date, $time, $waiter, $items, $amt] = $r;
            $bill = LegacyBill::firstOrCreate(
                ['source' => $this->option('source'), 'bill_date' => $date, 'bill_no' => $no],
                ['bill_time' => $time, 'waiter' => $waiter, 'items' => $items, 'amount' => (float) $amt, 'status' => 'unpaid']
            );
            if ($bill->wasRecentlyCreated) { $new++; $sum += (float) $amt; } else { $skip++; }
        }
        $this->info("Imported $new bills (KSh " . number_format($sum) . "), $skip already there.");
        return 0;
    }
}
