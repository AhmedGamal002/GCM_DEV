<?php

namespace Tests\Feature\Vehicles;

use App\Models\Asset;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vehicle categories are tenant-scoped and managed by the system_admin
 * (client request, outside the FRD's fixed five). Covers the CRUD, the
 * admin-only rule, the delete-blocked-while-in-use rule, per-tenant
 * isolation, and that the vehicle list's stats follow the real category
 * count.
 */
class VehicleCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $this->tenantA);

        $this->admin = $this->userWithRole('system_admin', 'admin@a.test');
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name_en' => 'Crane truck', 'name_ar' => 'مركبات رافعة'], $overrides);
    }

    public function test_a_new_tenant_starts_with_the_five_default_categories(): void
    {
        $this->assertSame(5, VehicleCategory::count());
        $this->assertEqualsCanonicalizing(
            array_keys(VehicleCategory::DEFAULTS),
            VehicleCategory::pluck('slug')->all()
        );
    }

    public function test_admin_can_create_a_category_and_it_appears_in_the_list(): void
    {
        $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicle-categories', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name_en', 'Crane truck')
            ->assertJsonPath('data.name_ar', 'مركبات رافعة')
            ->assertJsonPath('data.slug', 'crane_truck');

        $this->assertSame(6, VehicleCategory::count());
        $this->assertCount(6, $this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicle-categories')->json('data'));
    }

    public function test_renaming_keeps_the_slug_stable(): void
    {
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicle-categories/{$category->id}", $this->payload(['name_en' => 'Tipper', 'name_ar' => 'قلابات']))
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Tipper');

        $this->assertSame('dump_truck', $category->fresh()->slug);
    }

    public function test_slug_gets_a_numeric_suffix_when_the_generated_one_is_taken(): void
    {
        // "Dump  truck" slugs to dump_truck — already a default.
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload(['name_en' => 'Dump  truck!', 'name_ar' => 'قلابة ٢']))
            ->assertCreated();

        $this->assertSame('dump_truck_2', $response->json('data.slug'));
    }

    public function test_names_are_required_and_unique_within_the_tenant(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicle-categories', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_en', 'name_ar']);

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicle-categories', $this->payload(['name_en' => 'Dump truck']))
            ->assertUnprocessable()->assertJsonValidationErrors('name_en');

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicle-categories', $this->payload(['name_ar' => 'مركبات قلابة']))
            ->assertUnprocessable()->assertJsonValidationErrors('name_ar');
    }

    public function test_updating_a_category_with_its_own_names_does_not_reject_itself(): void
    {
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicle-categories/{$category->id}", ['name_en' => $category->name_en, 'name_ar' => $category->name_ar])
            ->assertOk();
    }

    #[DataProvider('nonAdminRolesProvider')]
    public function test_only_the_system_admin_can_manage_categories(string $role): void
    {
        $actor = $this->userWithRole($role, "{$role}@a.test");
        $category = VehicleCategory::firstOrFail();

        $this->actingAs($actor, 'web')->postJson('/api/v1/vehicle-categories', $this->payload())->assertForbidden();
        $this->actingAs($actor, 'web')->patchJson("/api/v1/vehicle-categories/{$category->id}", $this->payload())->assertForbidden();
        $this->actingAs($actor, 'web')->deleteJson("/api/v1/vehicle-categories/{$category->id}")->assertForbidden();
        $this->actingAs($actor, 'web')->getJson("/api/v1/vehicle-categories/{$category->id}")->assertForbidden();

        $this->assertSame(5, VehicleCategory::count());
    }

    public static function nonAdminRolesProvider(): array
    {
        return [['data_entry'], ['auditor'], ['driver']];
    }

    /** The plain list is reference data for every form dropdown — any tenant role reads it. */
    #[DataProvider('nonAdminRolesProvider')]
    public function test_every_role_can_still_read_the_category_list(string $role): void
    {
        $this->actingAs($this->userWithRole($role, "{$role}@a.test"), 'web')
            ->getJson('/api/v1/vehicle-categories')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $category = VehicleCategory::where('slug', 'dyna_box')->firstOrFail();

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$category->id}")->assertNoContent();

        $this->assertSame(4, VehicleCategory::count());
    }

    public function test_a_category_used_by_a_vehicle_cannot_be_deleted(): void
    {
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        Vehicle::factory()->count(2)->create(['vehicle_category_id' => $category->id]);

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, '2 vehicles'));

        $this->assertNotNull($category->fresh());
    }

    /**
     * The driver/asset pivots cascade on delete — without an explicit check
     * a delete would silently strip qualifications/compatibility instead of
     * failing. Same rule as for vehicles.
     */
    public function test_a_category_used_by_a_driver_or_an_asset_cannot_be_deleted(): void
    {
        $forDriver = VehicleCategory::where('slug', 'compactor')->firstOrFail();
        $forAsset = VehicleCategory::where('slug', 'water_tanker')->firstOrFail();

        $vehicle = Vehicle::factory()->create(['vehicle_category_id' => VehicleCategory::where('slug', 'hook_lift')->value('id')]);
        $driverUser = User::factory()->create();
        $driverUser->assignRole('driver');
        $driver = Driver::create([
            'user_id' => $driverUser->id, 'default_vehicle_id' => $vehicle->id,
            'residence_number' => 'R', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'L', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'O', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'I', 'insurance_valid_to' => '2030-01-01',
        ]);
        $driver->qualifiedVehicleCategories()->sync([$forDriver->id]);

        $asset = Asset::factory()->create();
        $asset->compatibleVehicleCategories()->sync([$forAsset->id]);

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$forDriver->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, '1 driver'));

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$forAsset->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, '1 asset'));

        $this->assertSame([$forDriver->id], $driver->fresh()->qualifiedVehicleCategories()->pluck('vehicle_categories.id')->all());
    }

    public function test_the_management_list_reports_usage_so_the_ui_can_disable_delete(): void
    {
        $used = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        Vehicle::factory()->create(['vehicle_category_id' => $used->id]);

        $rows = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicle-categories?per_page=10')->assertOk()->json('data'))
            ->keyBy('slug');

        $this->assertTrue($rows['dump_truck']['in_use']);
        $this->assertSame(1, $rows['dump_truck']['vehicles_count']);
        $this->assertFalse($rows['compactor']['in_use']);
    }

    public function test_the_management_list_searches_both_names(): void
    {
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicle-categories?per_page=10&search=Compactor')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicle-categories?per_page=10&search='.urlencode('قلابة'))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    /** Client-flagged: the vehicle list's category cards must match the real category count. */
    public function test_vehicle_stats_follow_the_real_category_list(): void
    {
        $crane = VehicleCategory::create(['slug' => 'crane', 'name_en' => 'Crane truck', 'name_ar' => 'رافعة']);
        Vehicle::factory()->count(3)->create(['vehicle_category_id' => $crane->id]);

        $byCategory = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles/stats')->json('data.by_category'));
        $this->assertCount(6, $byCategory);
        $this->assertSame(3, $byCategory->firstWhere('slug', 'crane')['count']);
    }

    public function test_deleting_a_category_removes_its_card(): void
    {
        $unused = VehicleCategory::where('slug', 'dyna_box')->firstOrFail();

        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$unused->id}")->assertNoContent();

        $slugs = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles/stats')->json('data.by_category'))->pluck('slug');
        $this->assertCount(4, $slugs);
        $this->assertFalse($slugs->contains('dyna_box'));
    }

    public function test_categories_are_isolated_between_tenants(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $tenantB);
        $adminB = $this->userWithRole('system_admin', 'admin@b.test');
        $foreignId = VehicleCategory::where('slug', 'dump_truck')->value('id');

        // B has its own five, and an admin of A can't reach B's row.
        $this->assertSame(5, VehicleCategory::count());
        app()->instance('tenant', $this->tenantA);
        $this->assertNotSame($foreignId, VehicleCategory::where('slug', 'dump_truck')->value('id'));

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/vehicle-categories/{$foreignId}")->assertNotFound();
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/vehicle-categories/{$foreignId}", $this->payload())->assertNotFound();
        $this->actingAs($this->admin, 'web')->deleteJson("/api/v1/vehicle-categories/{$foreignId}")->assertNotFound();
    }

    public function test_the_same_name_is_allowed_in_a_different_tenant(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicle-categories', $this->payload())->assertCreated();

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $tenantB);
        $adminB = $this->userWithRole('system_admin', 'admin@b.test');

        $this->actingAs($adminB, 'web')->postJson('/api/v1/vehicle-categories', $this->payload())->assertCreated();
    }

    public function test_the_management_pages_are_admin_only(): void
    {
        $category = VehicleCategory::firstOrFail();

        foreach (['/app/vehicle-category/list', '/app/vehicle-category/add', "/app/vehicle-category/edit/{$category->id}"] as $url) {
            $this->actingAs($this->admin, 'web')->get($url)->assertOk();
        }
    }

    #[DataProvider('nonAdminRolesProvider')]
    public function test_the_management_pages_are_forbidden_for_other_roles(string $role): void
    {
        $category = VehicleCategory::firstOrFail();
        $actor = $this->userWithRole($role, "{$role}@a.test");

        foreach (['/app/vehicle-category/list', '/app/vehicle-category/add', "/app/vehicle-category/edit/{$category->id}"] as $url) {
            $this->actingAs($actor, 'web')->get($url)->assertForbidden();
        }
    }
}
