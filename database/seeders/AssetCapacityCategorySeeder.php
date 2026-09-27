<?php

namespace Database\Seeders;

use App\Models\AssetCapacityCategory;
use Illuminate\Database\Seeder;

/**
 * Sample capacity categories for local/staging — feeds both the vehicle
 * form's "embedded container capacity" dropdown and the Assets module's
 * asset form. Tenant-scoped: the caller must have a bound tenant
 * (DatabaseSeeder binds one).
 */
class AssetCapacityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Small skip', 'applies_to' => 'container', 'capacity_cbm' => 6.00, 'capacity_ton' => 2.00],
            ['name' => 'Standard skip', 'applies_to' => 'container', 'capacity_cbm' => 10.00, 'capacity_ton' => 3.50],
            ['name' => 'Roll-on/off', 'applies_to' => 'container', 'capacity_cbm' => 20.00, 'capacity_ton' => 8.00],
            ['name' => 'Water tank', 'applies_to' => 'tank', 'capacity_cbm' => 12.00, 'capacity_ton' => 12.00],
            ['name' => 'Vacuum tanker', 'applies_to' => 'both', 'capacity_cbm' => 16.00, 'capacity_ton' => 14.00],
        ];

        foreach ($rows as $row) {
            AssetCapacityCategory::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
