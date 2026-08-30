<?php

namespace Tests\Feature\Drivers;

use App\Domain\Drivers\Actions\CreateDriverAction;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverExportTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create();
        $this->systemAdmin->assignRole('system_admin');

        app(CreateDriverAction::class)->execute(
            [
                'name' => 'Export Driver',
                'email' => 'exportdriver@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'status' => 'active',
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

    public function test_export_is_forbidden_for_non_system_admin(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')
            ->get('/api/v1/drivers/export')
            ->assertForbidden();
    }

    public function test_pdf_export_succeeds_for_system_admin(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->get('/api/v1/drivers/export?format=pdf')
            ->assertOk();
    }

    public function test_excel_export_succeeds_for_system_admin(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->get('/api/v1/drivers/export?format=xlsx')
            ->assertOk();
    }
}
