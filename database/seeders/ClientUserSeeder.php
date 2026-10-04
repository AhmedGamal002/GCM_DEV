<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two demo client accounts (FRD §1.4) so the client side can be tried in
 * local/staging: a project manager with access to ALL of Al Noor's projects
 * (also its representative), and a project auditor limited to one project
 * (also that project's representative). Tenant-scoped — needs the bound
 * tenant and the roles, and CompanySeeder / ProjectSeeder to have run.
 * Sign in with the factory's default password (`password`).
 */
class ClientUserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('prefix', 'ALN')->first();
        $tower = Project::where('name', 'Al Noor Tower')->first();

        if (! $company || ! $tower || User::where('email', 'client.manager@gcm.test')->exists()) {
            return;
        }

        $manager = User::factory()->client($company, 'client_project_manager', true)
            ->create(['name' => 'Client Project Manager', 'email' => 'client.manager@gcm.test', 'phone' => '+966500000101']);

        $auditor = User::factory()->client($company, 'client_project_auditor', false)
            ->create(['name' => 'Client Project Auditor', 'email' => 'client.auditor@gcm.test', 'phone' => '+966500000102']);
        $auditor->projects()->attach($tower);

        $company->forceFill(['representative_id' => $manager->id])->save();
        $tower->forceFill(['representative_id' => $auditor->id])->save();
    }
}
