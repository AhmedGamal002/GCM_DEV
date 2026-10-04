<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Contractor;
use App\Models\MainService;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * A few sample contractors (FRD V01.14 §1.10) so the Contractors list and its
 * statistics have real data in local/staging — with certified services (one
 * contractor supports everything, i.e. has none ticked) and a couple of
 * contractor-supplied assets. Tenant-scoped — needs the bound tenant that
 * DatabaseSeeder sets, and runs after ServiceSeeder / AssetSeeder.
 */
class ContractorSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Desert Haulage Co.', 'prefix' => 'DSH', 'trading_name' => 'Desert Trucks', 'phone' => '+966500000011', 'services' => ['Solid Waste Transport']],
            ['name' => 'Gulf Logistics', 'prefix' => 'GLG', 'phone' => '+966500000012', 'services' => []],
            ['name' => 'Al Safa Transport', 'prefix' => 'ASF', 'phone' => '+966500000013', 'services' => ['Hazardous Waste Transport', 'Water Supply'], 'status' => 'deactivated'],
        ];

        foreach ($rows as $row) {
            if (Contractor::where('prefix', $row['prefix'])->exists()) {
                continue;
            }

            $services = $row['services'];
            $status = $row['status'] ?? 'active';
            unset($row['services'], $row['status']);

            $contractor = new Contractor($row);
            $contractor->operational_status = $status;
            $contractor->save();

            $contractor->certifiedServices()->sync(MainService::whereIn('name', $services)->pluck('id')->all());
        }

        $this->seedContractorAssets();
    }

    /** Assets the first two contractors supply — they show in the contractor's counters. */
    private function seedContractorAssets(): void
    {
        $containerCapacity = AssetCapacityCategory::whereIn('applies_to', ['container', 'both'])->first();
        $tankCapacity = AssetCapacityCategory::whereIn('applies_to', ['tank', 'both'])->first();
        $hookLift = VehicleCategory::where('slug', 'hook_lift')->value('id');
        $waterTanker = VehicleCategory::where('slug', 'water_tanker')->value('id');

        $rows = [
            ['DSH', 'Container D-01', 'container', $containerCapacity, [$hookLift]],
            ['DSH', 'Container D-02', 'container', $containerCapacity, [$hookLift]],
            ['GLG', 'Tank G-01', 'tank', $tankCapacity, [$waterTanker]],
        ];

        foreach ($rows as [$prefix, $name, $type, $capacity, $categories]) {
            $contractor = Contractor::where('prefix', $prefix)->first();

            if (! $contractor || ! $capacity || Asset::where('name', $name)->exists()) {
                continue;
            }

            $asset = new Asset([
                'name' => $name,
                'asset_type' => $type,
                'asset_capacity_category_id' => $capacity->id,
                'affiliation' => 'contractor',
                'contractor_id' => $contractor->id,
            ]);
            $asset->operational_status = 'active';
            $asset->save();

            $asset->compatibleVehicleCategories()->sync(array_filter($categories));
        }
    }
}
