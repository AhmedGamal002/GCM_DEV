<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * A few sample client companies so the Client Companies list has real
 * data in local/staging. Tenant-scoped — needs the bound tenant that
 * DatabaseSeeder sets before calling this.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Al Noor Construction', 'prefix' => 'ALN', 'business_sector' => 'Construction', 'phone' => '+966500000001', 'email' => 'info@alnoor.test'],
            ['name' => 'Gulf Petrochemicals', 'prefix' => 'GPC', 'business_sector' => 'Oil & Gas', 'phone' => '+966500000002', 'email' => 'info@gulfpetro.test'],
            ['name' => 'Riyadh Real Estate Group', 'prefix' => 'RRE', 'business_sector' => 'Real estate', 'status' => 'deactivated'],
        ];

        foreach ($rows as $row) {
            if (Company::where('prefix', $row['prefix'])->exists()) {
                continue;
            }

            $status = $row['status'] ?? 'active';
            unset($row['status']);

            $company = new Company($row);
            $company->operational_status = $status;
            $company->save();
        }
    }
}
