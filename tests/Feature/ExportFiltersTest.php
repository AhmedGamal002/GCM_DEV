<?php

namespace Tests\Feature;

use App\Domain\Drivers\Actions\CreateDriverAction;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Company;
use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * An export must hold exactly the rows the user sees on screen: the search
 * box and every active filter. The buttons used to send only `format`, and
 * the API ignored `search` on export, so a filtered list exported the whole
 * table. Each list's index() and export() now share one filteredQuery().
 */
class ExportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'T', 'slug' => 't', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        Excel::fake();
    }

    /** The rows handed to the Excel export for this request. */
    private function exported(string $url, string $file): Collection
    {
        $this->actingAs($this->admin, 'web')->get($url)->assertOk();

        $rows = collect();
        Excel::assertDownloaded($file, function ($export) use (&$rows) {
            $rows = $export->collection();

            return true;
        });

        return $rows;
    }

    public function test_vehicle_export_honours_search_and_every_filter(): void
    {
        $dump = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $compactor = VehicleCategory::where('slug', 'compactor')->firstOrFail();

        Vehicle::factory()->create(['plate_letters' => 'AAA', 'plate_numbers' => '1111', 'vehicle_category_id' => $dump->id, 'operational_status' => 'active', 'affiliation' => 'gcm']);
        Vehicle::factory()->create(['plate_letters' => 'BBB', 'plate_numbers' => '2222', 'vehicle_category_id' => $dump->id, 'operational_status' => 'on_maintenance', 'affiliation' => 'gcm']);
        Vehicle::factory()->create(['plate_letters' => 'CCC', 'plate_numbers' => '3333', 'vehicle_category_id' => $compactor->id, 'operational_status' => 'active', 'affiliation' => 'gcm']);

        $this->assertCount(3, $this->exported('/api/v1/vehicles/export?format=xlsx', 'vehicles.xlsx'));

        $this->assertSame(['BBB'], $this->exported('/api/v1/vehicles/export?format=xlsx&search=BBB', 'vehicles.xlsx')->pluck('plate_letters')->all());
        $this->assertSame(['BBB'], $this->exported('/api/v1/vehicles/export?format=xlsx&operational_status=on_maintenance', 'vehicles.xlsx')->pluck('plate_letters')->all());
        $this->assertSame(['CCC'], $this->exported('/api/v1/vehicles/export?format=xlsx&category=compactor', 'vehicles.xlsx')->pluck('plate_letters')->all());
        // filters and search combine
        $this->assertSame(['AAA'], $this->exported('/api/v1/vehicles/export?format=xlsx&category=dump_truck&operational_status=active&search=AAA', 'vehicles.xlsx')->pluck('plate_letters')->all());
        $this->assertCount(0, $this->exported('/api/v1/vehicles/export?format=xlsx&category=compactor&search=AAA', 'vehicles.xlsx'));
    }

    public function test_the_pdf_export_is_filtered_the_same_way(): void
    {
        Vehicle::factory()->create(['plate_letters' => 'AAA', 'plate_numbers' => '1111']);
        Vehicle::factory()->create(['plate_letters' => 'BBB', 'plate_numbers' => '2222']);

        $response = $this->actingAs($this->admin, 'web')->get('/api/v1/vehicles/export?format=pdf&search=BBB');

        $response->assertOk()->assertDownload('vehicles.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_user_export_honours_search_status_and_role(): void
    {
        $alpha = User::factory()->create(['name' => 'Alpha Tester', 'status' => 'active']);
        $alpha->assignRole('data_entry');
        $beta = User::factory()->create(['name' => 'Beta Other', 'status' => 'on_vacation']);
        $beta->assignRole('auditor');

        // the admin's own row is never listed (same as the list)
        $this->assertCount(2, $this->exported('/api/v1/users/export?format=xlsx', 'users.xlsx'));

        $this->assertSame(['Alpha Tester'], $this->exported('/api/v1/users/export?format=xlsx&search=Alpha', 'users.xlsx')->pluck('name')->all());
        $this->assertSame(['Beta Other'], $this->exported('/api/v1/users/export?format=xlsx&status=on_vacation', 'users.xlsx')->pluck('name')->all());
        $this->assertSame(['Alpha Tester'], $this->exported('/api/v1/users/export?format=xlsx&role=data_entry', 'users.xlsx')->pluck('name')->all());
        // the `code` column is searchable too, like the list
        $this->assertSame(['Beta Other'], $this->exported('/api/v1/users/export?format=xlsx&search='.$beta->code, 'users.xlsx')->pluck('name')->all());
    }

    public function test_asset_export_honours_search_and_filters(): void
    {
        $cap = AssetCapacityCategory::factory()->create(['applies_to' => 'both']);
        Asset::factory()->create(['name' => 'Box One', 'asset_type' => 'container', 'asset_capacity_category_id' => $cap->id]);
        Asset::factory()->create(['name' => 'Tank Two', 'asset_type' => 'tank', 'asset_capacity_category_id' => $cap->id]);

        $this->assertCount(2, $this->exported('/api/v1/assets/export?format=xlsx', 'assets.xlsx'));
        $this->assertSame(['Tank Two'], $this->exported('/api/v1/assets/export?format=xlsx&asset_type=tank', 'assets.xlsx')->pluck('name')->all());
        $this->assertSame(['Box One'], $this->exported('/api/v1/assets/export?format=xlsx&search=Box', 'assets.xlsx')->pluck('name')->all());
    }

    public function test_client_company_export_honours_search_and_filter(): void
    {
        Company::factory()->create(['name' => 'Alpha Build', 'prefix' => 'ALP']);
        Company::factory()->deactivated()->create(['name' => 'Beta Gulf', 'prefix' => 'BET']);

        $this->assertCount(2, $this->exported('/api/v1/companies/export?format=xlsx', 'client-companies.xlsx'));
        $this->assertSame(['Beta Gulf'], $this->exported('/api/v1/companies/export?format=xlsx&operational_status=deactivated', 'client-companies.xlsx')->pluck('name')->all());
        $this->assertSame(['Alpha Build'], $this->exported('/api/v1/companies/export?format=xlsx&search=Alpha', 'client-companies.xlsx')->pluck('name')->all());
        // the short name is searchable too
        $this->assertSame(['Beta Gulf'], $this->exported('/api/v1/companies/export?format=xlsx&search=BET', 'client-companies.xlsx')->pluck('name')->all());
    }

    public function test_asset_category_export_honours_search_and_filter(): void
    {
        AssetCapacityCategory::factory()->create(['name' => 'Small Box', 'applies_to' => 'container']);
        AssetCapacityCategory::factory()->create(['name' => 'Big Tank', 'applies_to' => 'tank']);

        $this->assertCount(2, $this->exported('/api/v1/asset-capacity-categories/export?format=xlsx', 'asset-capacity-categories.xlsx'));
        $this->assertSame(['Big Tank'], $this->exported('/api/v1/asset-capacity-categories/export?format=xlsx&applies_to=tank', 'asset-capacity-categories.xlsx')->pluck('name')->all());
        $this->assertSame(['Small Box'], $this->exported('/api/v1/asset-capacity-categories/export?format=xlsx&search=Small', 'asset-capacity-categories.xlsx')->pluck('name')->all());
    }

    public function test_vehicle_category_export_honours_search_in_both_languages(): void
    {
        // The tenant already holds the primary defaults (Tenant::created).
        $all = $this->exported('/api/v1/vehicle-categories/export?format=xlsx', 'vehicle-categories.xlsx');
        $this->assertCount(count(VehicleCategory::DEFAULTS), $all);

        $this->assertSame(['tractor_truck'], $this->exported('/api/v1/vehicle-categories/export?format=xlsx&search=Tractor', 'vehicle-categories.xlsx')->pluck('slug')->all());
        $this->assertSame(['tractor_truck'], $this->exported('/api/v1/vehicle-categories/export?format=xlsx&search='.urlencode('جرّارة'), 'vehicle-categories.xlsx')->pluck('slug')->all());
        $this->assertCount(0, $this->exported('/api/v1/vehicle-categories/export?format=xlsx&search=zzz-nothing', 'vehicle-categories.xlsx'));
    }

    public function test_vehicle_category_export_pdf_works(): void
    {
        $this->actingAs($this->admin, 'web')->get('/api/v1/vehicle-categories/export?format=pdf&search=Tractor')->assertOk();
    }

    public function test_vehicle_category_export_is_admin_only(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        $this->actingAs($auditor, 'web')->get('/api/v1/vehicle-categories/export?format=xlsx')->assertForbidden();
    }

    public function test_facility_export_honours_search_and_filters(): void
    {
        IntermediateFacility::factory()->create(['name' => 'Alpha Landfill', 'prefix' => 'ALP']);
        IntermediateFacility::factory()->recycling()->create(['name' => 'Beta Recycler', 'prefix' => 'BET']);
        IntermediateFacility::factory()->sewage()->deactivated()->create(['name' => 'Gamma Works', 'prefix' => 'GAM']);

        $names = fn (string $qs) => $this->exported('/api/v1/facilities/export?format=xlsx'.$qs, 'facilities.xlsx')->pluck('name')->all();

        $this->assertCount(3, $this->exported('/api/v1/facilities/export?format=xlsx', 'facilities.xlsx'));
        $this->assertSame(['Beta Recycler'], $names('&environmental_service=recycle'));
        $this->assertSame(['Gamma Works'], $names('&operational_status=deactivated'));
        $this->assertSame(['Alpha Landfill'], $names('&search=Alpha'));
        $this->assertSame(['Beta Recycler'], $names('&search=BET'));
        $this->assertSame([], $names('&environmental_service=recycle&search=Alpha'));
    }

    public function test_facility_export_pdf_works(): void
    {
        IntermediateFacility::factory()->recycling()->create(['name' => 'مصنع التدوير', 'prefix' => 'REC']);

        $this->actingAs($this->admin, 'web')->get('/api/v1/facilities/export?format=pdf&search=REC')->assertOk();
    }

    public function test_driver_export_honours_search_and_filters(): void
    {
        $this->makeDriver('Zed Driver', 'zed@t.test', '01000000001', 'active');
        $this->makeDriver('Omar Driver', 'omar@t.test', '01000000002', 'on_vacation');

        $names = fn (string $qs) => $this->exported('/api/v1/drivers/export?format=xlsx'.$qs, 'drivers.xlsx')
            ->map(fn ($d) => $d->user->name)->all();

        $this->assertCount(2, $names(''));
        $this->assertSame(['Zed Driver'], $names('&search=Zed'));
        $this->assertSame(['Omar Driver'], $names('&status=on_vacation'));
        $this->assertSame([], $names('&status=on_vacation&search=Zed'));
    }

    private function makeDriver(string $name, string $email, string $phone, string $status): void
    {
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $vehicle = Vehicle::factory()->create(['vehicle_category_id' => $category->id]);

        app(CreateDriverAction::class)->execute(
            [
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'password' => 'a-secure-password', 'status' => $status,
                'vehicle_category_ids' => [$category->id],
                'default_vehicle_id' => $vehicle->id,
                'residence_number' => 'RES-1', 'residence_valid_to' => '2030-01-01',
                'license_number' => 'LIC-1', 'license_valid_to' => '2030-01-01',
                'operational_license_number' => 'OP-1', 'operational_license_valid_to' => '2030-01-01',
                'insurance_number' => 'INS-1', 'insurance_valid_to' => '2030-01-01',
            ],
            null,
            [],
            []
        );
    }
}
