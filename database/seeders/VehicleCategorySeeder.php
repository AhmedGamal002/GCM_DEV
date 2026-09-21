<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * Makes sure every existing tenant has the five FRD vehicle categories
 * (§1.5.2). New tenants get them automatically from Tenant::created, so
 * this only matters for tenants that predate that hook — it never touches
 * categories a tenant has since renamed or added.
 */
class VehicleCategorySeeder extends Seeder
{
    public function run(): void
    {
        Tenant::all()->each(fn (Tenant $tenant) => VehicleCategory::seedDefaultsFor($tenant));
    }
}
