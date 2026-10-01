<?php

namespace Database\Seeders;

use App\Models\AssetCapacityCategory;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * Sample main services (one per type of service) with sub-services, linked
 * to the seeded vehicle categories, capacities and facilities — so the
 * Services pages and a facility's "supported sub-services" have real data
 * in local/staging. Tenant-scoped: needs the bound tenant DatabaseSeeder
 * sets, and runs after AssetCapacityCategorySeeder / IntermediateFacilitySeeder.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $category = fn (array $slugs) => VehicleCategory::whereIn('slug', $slugs)->pluck('id')->all();
        $capacity = fn (array $names) => AssetCapacityCategory::whereIn('name', $names)->pluck('id')->all();
        $facility = fn (array $prefixes) => IntermediateFacility::whereIn('prefix', $prefixes)->pluck('id')->all();

        $services = [
            [
                'name' => 'Solid Waste Transport',
                'service_type' => 'non_hazardous',
                'categories' => ['hook_lift', 'compactor', 'dump_truck', 'dyna_box', 'tractor_truck'],
                'subs' => [
                    ['name' => 'Construction debris', 'asset_type' => 'container', 'capacities' => ['Standard skip', 'Roll-on/off'], 'facilities' => ['ALF', 'GLR'], 'receipt' => true, 'color' => 'light_concrete'],
                    ['name' => 'General waste', 'asset_type' => 'container', 'capacities' => ['Small skip', 'Standard skip'], 'facilities' => ['ALF'], 'color' => 'green_general'],
                    ['name' => 'Cardboard & paper', 'asset_type' => 'container', 'capacities' => ['Small skip'], 'facilities' => ['GLR'], 'receipt' => true, 'color' => 'brown_cardboard'],
                ],
            ],
            [
                'name' => 'Hazardous Waste Transport',
                'service_type' => 'hazardous',
                'categories' => ['hook_lift', 'water_tanker', 'tractor_truck'],
                'subs' => [
                    ['name' => 'Oily sludge', 'asset_type' => 'tank', 'capacities' => ['Water tank', 'Vacuum tanker'], 'facilities' => ['NST'], 'color' => 'black_hazardous'],
                    ['name' => 'Contaminated soil', 'asset_type' => 'container', 'capacities' => ['Standard skip'], 'facilities' => ['ALF'], 'color' => 'black_hazardous'],
                ],
            ],
            [
                'name' => 'Water Supply',
                'service_type' => 'supplying',
                'categories' => ['water_tanker'],
                'subs' => [
                    // Supplying: no intermediate facility needed (FRD §1.9.2 note).
                    ['name' => 'Potable water', 'asset_type' => 'tank', 'capacities' => ['Water tank'], 'facilities' => []],
                ],
            ],
        ];

        foreach ($services as $row) {
            if (MainService::where('name', $row['name'])->exists()) {
                continue;
            }

            $main = new MainService(['name' => $row['name']]);
            $main->service_type = $row['service_type'];
            $main->operational_status = 'active';
            $main->save();
            $main->vehicleCategories()->sync($category($row['categories']));

            foreach ($row['subs'] as $s) {
                $sub = new SubService([
                    'name' => $s['name'],
                    'asset_type' => $s['asset_type'],
                    'requires_recycle_receipt' => $s['receipt'] ?? false,
                    'skip_color_code' => $s['color'] ?? null,
                ]);
                $sub->main_service_id = $main->id;
                $sub->operational_status = 'active';
                $sub->save();
                $sub->capacities()->sync($capacity($s['capacities']));
                $sub->facilities()->sync($facility($s['facilities']));
            }
        }
    }
}
