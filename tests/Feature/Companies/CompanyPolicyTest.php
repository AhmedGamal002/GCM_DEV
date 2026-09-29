<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.11: (create / edit / deactivate) = System Admin / Data
 * Entry; Auditor = view + export only; Driver = nothing.
 */
class CompanyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@gcm.test"]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('managerRoles')]
    public function test_system_admin_and_data_entry_have_full_access(string $role): void
    {
        $company = Company::factory()->create();
        $actor = $this->actor($role);

        $this->actingAs($actor, 'web')->getJson('/api/v1/companies')->assertOk();
        $this->actingAs($actor, 'web')->getJson("/api/v1/companies/{$company->id}")->assertOk();
        $this->actingAs($actor, 'web')->get('/api/v1/companies/export')->assertOk();
        $this->actingAs($actor, 'web')->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'deactivated'])->assertOk();
    }

    public static function managerRoles(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_auditor_can_view_and_export_but_not_change_anything(): void
    {
        $company = Company::factory()->create();
        $auditor = $this->actor('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/companies')->assertOk();
        $this->actingAs($auditor, 'web')->getJson("/api/v1/companies/{$company->id}")->assertOk();
        $this->actingAs($auditor, 'web')->get('/api/v1/companies/export')->assertOk();

        $this->actingAs($auditor, 'web')->postJson('/api/v1/companies', ['name' => 'X', 'prefix' => 'XXX', 'operational_status' => 'active'])->assertForbidden();
        $this->actingAs($auditor, 'web')->patchJson("/api/v1/companies/{$company->id}", ['name' => 'X', 'prefix' => 'XXX'])->assertForbidden();
        $this->actingAs($auditor, 'web')->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_driver_has_no_access_at_all(): void
    {
        $company = Company::factory()->create();
        $driver = $this->actor('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/companies')->assertForbidden();
        $this->actingAs($driver, 'web')->getJson("/api/v1/companies/{$company->id}")->assertForbidden();
        $this->actingAs($driver, 'web')->get('/api/v1/companies/export')->assertForbidden();
        $this->actingAs($driver, 'web')->postJson('/api/v1/companies', [])->assertForbidden();
    }

    public function test_the_attachment_download_needs_view_access(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->actor('driver'), 'web')
            ->get("/api/v1/companies/{$company->id}/documents/contract")
            ->assertForbidden();
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/companies')->assertUnauthorized();
    }
}
