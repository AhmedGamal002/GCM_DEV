<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * A handful of sample assets so the Assets list / stat cards have real
 * data in local/staging. Tenant-scoped — needs the bound tenant that
 * DatabaseSeeder sets before calling this.
 */
class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $containerCapacity = AssetCapacityCategory::whereIn('applies_to', ['container', 'both'])->first()
            ?? AssetCapacityCategory::factory()->container()->create();
        $tankCapacity = AssetCapacityCategory::whereIn('applies_to', ['tank', 'both'])->first()
            ?? AssetCapacityCategory::factory()->tank()->create();

        $cat = fn (string $slug) => VehicleCategory::where('slug', $slug)->value('id');
        $hookLift = $cat('hook_lift');
        $compactor = $cat('compactor');
        $dumpTruck = $cat('dump_truck');
        $dynaBox = $cat('dyna_box');
        $waterTanker = $cat('water_tanker');

        $rows = [
            ['name' => 'Container A-01', 'asset_type' => 'container', 'asset_capacity_category_id' => $containerCapacity->id, 'cats' => array_filter([$hookLift, $compactor])],
            ['name' => 'Container A-02', 'asset_type' => 'container', 'asset_capacity_category_id' => $containerCapacity->id, 'cats' => array_filter([$hookLift, $dumpTruck])],
            ['name' => 'Container A-03', 'asset_type' => 'container', 'asset_capacity_category_id' => $containerCapacity->id, 'cats' => array_filter([$hookLift, $dynaBox]), 'status' => 'on_maintenance'],
            ['name' => 'Tank T-01', 'asset_type' => 'tank', 'asset_capacity_category_id' => $tankCapacity->id, 'cats' => array_filter([$waterTanker])],
            ['name' => 'Tank T-02', 'asset_type' => 'tank', 'asset_capacity_category_id' => $tankCapacity->id, 'cats' => array_filter([$waterTanker]), 'status' => 'deactivated'],
        ];

        foreach ($rows as $row) {
            if (Asset::where('name', $row['name'])->exists()) {
                continue;
            }

            $asset = Asset::create([
                'name' => $row['name'],
                'asset_type' => $row['asset_type'],
                'asset_capacity_category_id' => $row['asset_capacity_category_id'],
                'affiliation' => 'gcm',
            ]);

            if (isset($row['status'])) {
                $asset->operational_status = $row['status'];
                $asset->save();
            }

            $asset->compatibleVehicleCategories()->sync($row['cats']);
        }
    }
}
