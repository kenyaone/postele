<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InspectPriceList extends Command
{
    protected $signature = 'pos:inspect-pricelist {path=storage/app/Tele_Cafe_Price_List.xlsx}';
    protected $description = 'Dump the first 20 rows of each sheet in the price list for layout inspection';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));
        if (! file_exists($path)) {
            $this->error("Not found: $path");
            return 1;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);

        foreach ($ss->getSheetNames() as $name) {
            $this->line("=== Sheet: $name ===");
            $sheet = $ss->getSheetByName($name);
            $rows = $sheet->toArray(null, true, true, true);
            $i = 0;
            foreach ($rows as $rnum => $row) {
                $vals = array_map(fn($v) => is_null($v) ? '.' : (string) $v, $row);
                $this->line("row $rnum: " . implode(' | ', $vals));
                if (++$i >= 20) break;
            }
            $this->line('total rows: ' . count($rows));
            $this->line('');
        }

        return 0;
    }
}
