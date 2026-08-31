<?php

namespace Database\Seeders;

use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * The five fixed vehicle categories from FRD §1.5.2. Global (no tenant),
 * same as RoleSeeder — never user-editable, no CRUD.
 */
class VehicleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'hook_lift', 'name_en' => 'Hook lift system', 'name_ar' => 'مركبات بنظام خطاف رفع'],
            ['slug' => 'compactor', 'name_en' => 'Compactor unit', 'name_ar' => 'مركبات بوحدة ضغط'],
            ['slug' => 'dump_truck', 'name_en' => 'Dump truck', 'name_ar' => 'مركبات قلابة'],
            ['slug' => 'water_tanker', 'name_en' => 'Water tanker', 'name_ar' => 'مركبات صهريج مياه'],
            ['slug' => 'dyna_box', 'name_en' => 'Dyna box', 'name_ar' => 'مركبات بصندوق مغلق'],
        ];

        foreach ($categories as $category) {
            VehicleCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
