<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * A few sample projects so the Projects list (and the projects section of
 * a company's page) has real data in local/staging, plus one asset placed
 * in a project so "in a project" shows up. Tenant-scoped — needs the bound
 * tenant that DatabaseSeeder sets, and CompanySeeder / AssetSeeder to have
 * run first.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['company' => 'ALN', 'name' => 'Al Noor Tower', 'operational_region' => 'Riyadh', 'address' => 'King Fahd Road, Riyadh'],
            ['company' => 'ALN', 'name' => 'Al Noor Residential Complex', 'operational_region' => 'Jeddah'],
            ['company' => 'GPC', 'name' => 'Gulf Petro Plant Expansion', 'operational_region' => 'Jubail', 'phone' => '+966500000010'],
            ['company' => 'GPC', 'name' => 'Gulf Petro Warehouse', 'operational_region' => 'Dammam', 'status' => 'deactivated'],
        ];

        foreach ($rows as $row) {
            $company = Company::where('prefix', $row['company'])->first();

            if (! $company || Project::where('company_id', $company->id)->where('name', $row['name'])->exists()) {
                continue;
            }

            $status = $row['status'] ?? 'active';
            unset($row['company'], $row['status']);

            $project = new Project($row);
            $project->company_id = $company->id;
            $project->operational_status = $status;
            $project->save();
        }

        // Leave one available container at the first project.
        $asset = Asset::where('name', 'Container A-01')->whereNull('project_id')->first();
        $project = Project::where('name', 'Al Noor Tower')->first();

        if ($asset && $project) {
            $asset->project_id = $project->id;
            $asset->save();
        }
    }
}
