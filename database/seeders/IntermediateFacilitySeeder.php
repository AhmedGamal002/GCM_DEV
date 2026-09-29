<?php

namespace Database\Seeders;

use App\Models\IntermediateFacility;
use Illuminate\Database\Seeder;

/**
 * A few sample facilities — one per environmental service, plus a
 * deactivated one — so the Facilities list / stat cards have real data in
 * local/staging. Tenant-scoped — needs the bound tenant that DatabaseSeeder
 * sets before calling this.
 */
class IntermediateFacilitySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Al Ain Safe Landfill', 'prefix' => 'ALF', 'environmental_service' => 'disposal', 'address' => 'Al Ain Industrial Area'],
            ['name' => 'Green Loop Recycling Plant', 'prefix' => 'GLR', 'environmental_service' => 'recycle', 'recycling_efficiency' => 72.5, 'address' => 'Abu Dhabi, ICAD'],
            ['name' => 'North Sewage Treatment Works', 'prefix' => 'NST', 'environmental_service' => 'sewage_treatment', 'address' => 'Sharjah'],
            ['name' => 'Old Quarry Landfill', 'prefix' => 'OQL', 'environmental_service' => 'disposal', 'status' => 'deactivated'],
        ];

        foreach ($rows as $row) {
            if (IntermediateFacility::where('prefix', $row['prefix'])->exists()) {
                continue;
            }

            $facility = IntermediateFacility::create([
                'name' => $row['name'],
                'prefix' => $row['prefix'],
                'environmental_service' => $row['environmental_service'],
                'recycling_efficiency' => $row['recycling_efficiency'] ?? null,
                'address' => $row['address'] ?? null,
            ]);

            if (isset($row['status'])) {
                $facility->operational_status = $row['status'];
                $facility->save();
            }
        }
    }
}
