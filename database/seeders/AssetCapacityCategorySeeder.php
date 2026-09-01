<?php

namespace Database\Seeders;

use App\Models\AssetCapacityCategory;
use Illuminate\Database\Seeder;

/**
 * Sample capacity categories so the vehicle form's "embedded container
 * capacity" dropdown has real options in local/staging. Tenant-scoped —
 * the caller must have a bound tenant (DatabaseSeeder binds one).
 */
class AssetCapacityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Small skip', 'capacity_cbm' => 6.00, 'capacity_ton' => 2.00],
            ['name' => 'Standard skip', 'capacity_cbm' => 10.00, 'capacity_ton' => 3.50],
            ['name' => 'Roll-on/off', 'capacity_cbm' => 20.00, 'capacity_ton' => 8.00],
            ['name' => 'Water tank', 'capacity_cbm' => 12.00, 'capacity_ton' => 12.00],
        ];

        foreach ($rows as $row) {
            AssetCapacityCategory::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
