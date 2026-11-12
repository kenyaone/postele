<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/** Tele Cafe supplier list (Nov 2026). Safe to re-run: matches on supplier name. */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // name, contact, phone, category, terms
            ['Wairimu', 'Beatrice Wairumi', '0712346684', 'Produce', 'COD'],
            ['Paustine', 'Paustine', '0723874477', 'Produce', 'COD'],
            ['Zawadi Cereals', 'Zawadi', '0726176081', 'Dry Goods', 'COD'],
            ['Chirowaonline', 'Chirowaonline', '0756188533', 'Dry Goods', 'COD'],
            ['Shop', 'Bramuel', '0729276203', 'Produce', 'COD'],
            ['Bhayko Distributors', 'Bakhyako', '073868655', 'Produce', 'COD'],
            ['Soda Distributors', 'Sam', '0712464936', 'Beverages', 'COD'],
            ['Naivas', 'Naiva', '+254712464936', 'Dairy', 'COD'],
            ['Eldomatt', 'Sam', '07886544', 'Produce', 'COD'],
            ['Mikeline', 'Mikeline', '0729642007', 'Packaging', 'COD'],
            ['Pork Kichinjio', 'Jimmy', '0725467113', 'Meat & Fish', 'COD'],
            ['Fish Point', '-', '-', 'Meat & Fish', 'COD'],
            ['Manzini', '-', '-', 'Meat & Fish', '-'],
            ['Bahati Market', 'Soko', '0717489513', 'Produce', 'COD'],
            ['Wakili', '-', '-', 'Dairy', 'COD'],
            ['Mama Dota', '-', '-', 'Meat & Fish', '-'],
            ['Tiarana Cash & Carry', '-', '0738686555', 'Dry Goods', 'COD'],
            ['Lelan Aqua', '-', '-', 'Beverages', 'COD'],
            ['Royal Tea Suppliers Ltd', '-', '0720051781', 'Produce', 'COD'],
            ['Sokoni Traders', 'Traders', '0726176081', 'Packaging', 'COD'],
            ['Nickson Mboga', 'Nick', '0726176081', 'Produce', 'COD'],
            ['Beef Kichinjio', 'Shooting Star', '0723795757', 'Meat & Fish', 'COD'],
            ['Klasique Groceries', 'Grace Njeri', '0726176081', '-', '-'],
            ['Mala Vendor', 'Grace Njeri', '-', 'Dairy', 'COD'],
            ['Dhoni Emporium', '-', '0721224422', 'Packaging', 'COD'],
        ];

        $blank = fn ($v) => ($v === '-' || trim($v) === '') ? null : trim($v);
        foreach ($rows as [$name, $contact, $phone, $category, $terms]) {
            Supplier::updateOrCreate(['name' => $name], [
                'contact' => $blank($contact), 'phone' => $blank($phone),
                'category' => $blank($category), 'terms' => $blank($terms),
            ]);
        }
        $this->command?->info(count($rows) . ' suppliers imported/updated.');
    }
}
