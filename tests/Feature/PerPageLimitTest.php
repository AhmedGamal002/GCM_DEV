<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A list endpoint never returns more than one capped page, whatever `per_page` asks for.
 */
class PerPageLimitTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web');
    }

    public function test_per_page_is_capped_and_bad_values_fall_back_to_the_default(): void
    {
        $this->actAsAdmin();
        Company::factory()->count(3)->create();

        $this->getJson('/api/v1/companies?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 1000);
        $this->getJson('/api/v1/companies?per_page=200')->assertOk()->assertJsonPath('meta.per_page', 200);
        $this->getJson('/api/v1/companies?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->getJson('/api/v1/companies?per_page=-5')->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->getJson('/api/v1/companies')->assertOk()->assertJsonPath('meta.per_page', 15);
    }

    public function test_the_purchase_order_list_is_capped_too(): void
    {
        $this->actAsAdmin();

        $this->getJson('/api/v1/purchase-orders?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 1000);
        $this->getJson('/api/v1/purchase-orders?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 15);
    }
}
