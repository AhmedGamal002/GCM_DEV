<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two demo contractor users (FRD §1.4) so the contractor side can be tried in
 * local/staging: one for Desert Haulage Co. (also its representative) and one
 * for Gulf Logistics. Tenant-scoped — needs the bound tenant and the roles,
 * and ContractorSeeder to have run. Sign in with the factory's default
 * password (`password`).
 */
class ContractorUserSeeder extends Seeder
{
    public function run(): void
    {
        $desert = Contractor::where('prefix', 'DSH')->first();
        $gulf = Contractor::where('prefix', 'GLG')->first();

        if (! $desert || ! $gulf || User::where('email', 'contractor.desert@gcm.test')->exists()) {
            return;
        }

        $desertUser = User::factory()->contractorUser($desert)
            ->create(['name' => 'Desert Haulage User', 'email' => 'contractor.desert@gcm.test', 'phone' => '+966500000201']);

        User::factory()->contractorUser($gulf)
            ->create(['name' => 'Gulf Logistics User', 'email' => 'contractor.gulf@gcm.test', 'phone' => '+966500000202']);

        $desert->forceFill(['representative_id' => $desertUser->id])->save();
    }
}
