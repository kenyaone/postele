<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/Tele_Cafe_Price_List.xlsx');
        if (! file_exists($path)) {
            $this->command->error("Price list not found at $path");
            return;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);
        $sheet = $ss->getSheetByName('Price List') ?? $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Header is row 4 (# | Item | Category | Price (KES) | Status).
        // Data starts row 5.
        $categoryIds = [];
        $sortOrder = 0;
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$categoryIds, &$sortOrder, &$imported, &$skipped) {
            foreach ($rows as $rnum => $row) {
                if ($rnum < 5) continue;
                $name = trim((string) ($row['B'] ?? ''));
                $cat  = trim((string) ($row['C'] ?? ''));
                $priceRaw = $row['D'] ?? null;

                if ($name === '' || $cat === '' || $priceRaw === null || $priceRaw === '') {
                    $skipped++;
                    continue;
                }

                $price = (float) preg_replace('/[^0-9.]/', '', (string) $priceRaw);
                if ($price <= 0) {
                    $skipped++;
                    continue;
                }

                if (! isset($categoryIds[$cat])) {
                    $categoryIds[$cat] = DB::table('categories')->insertGetId([
                        'name'       => $cat,
                        'sort_order' => $sortOrder++,
                        'active'     => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('items')->insert([
                    'category_id' => $categoryIds[$cat],
                    'name'        => $name,
                    'price'       => $price,
                    'station'     => 'kitchen',
                    'active'      => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $imported++;
            }
        });

        $this->command->info("Imported: $imported items into " . count($categoryIds) . " categories (skipped: $skipped)");
    }
}
