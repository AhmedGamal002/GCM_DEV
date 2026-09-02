<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * ONE-OFF dev tool, not part of the standard `migrate:fresh --seed` chain
 * (not registered in DatabaseSeeder) — generates a large batch of vehicles
 * so the server-side-pagination fix can be eyeballed with realistic volume
 * (client has ~8000 employees; this exercises the Vehicles list the same
 * way). Run explicitly: `php artisan db:seed --class=DevVehicleVolumeSeeder`.
 *
 * Uses a raw bulk insert() instead of Vehicle::factory()->create() in a
 * loop — 3000 individual Eloquent inserts is needlessly slow for what is
 * disposable dev data; nothing here needs model events.
 */
class DevVehicleVolumeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'gcm')->firstOrFail();
        app()->instance('tenant', $tenant);

        $categoryIds = VehicleCategory::pluck('id')->all();
        $statuses = ['active', 'active', 'active', 'on_maintenance', 'deactivated'];
        $affiliations = ['gcm', 'gcm', 'gcm', 'contractor'];

        $count = 3000;
        $now = now();
        $letters = range('A', 'Z');

        $batch = [];
        for ($i = 0; $i < $count; $i++) {
            $plateLetters = $letters[intdiv($i, 26 * 26) % 26].$letters[intdiv($i, 26) % 26].$letters[$i % 26];
            $plateNumbers = str_pad((string) (($i % 9999) + 1), 4, '0', STR_PAD_LEFT);

            $batch[] = [
                'tenant_id' => $tenant->id,
                'plate_letters' => $plateLetters,
                'plate_numbers' => $plateNumbers,
                'vehicle_category_id' => $categoryIds[$i % count($categoryIds)],
                'has_embedded_container' => false,
                'embedded_container_type' => null,
                'embedded_asset_capacity_category_id' => null,
                'operational_status' => $statuses[$i % count($statuses)],
                'affiliation' => $affiliations[$i % count($affiliations)],
                'additional_data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 500) {
                Vehicle::insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            Vehicle::insert($batch);
        }

        $this->command?->info("Inserted {$count} vehicles for tenant '{$tenant->slug}'.");
    }
}
