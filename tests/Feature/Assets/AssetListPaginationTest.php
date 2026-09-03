<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same server-side-processing regression class as
 * VehicleListPaginationTest — the assets list must genuinely paginate,
 * sort and search on the server, not fetch everything once.
 */
class AssetListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        AssetCapacityCategory::factory()->container()->create();
    }

    public function test_a_small_per_page_still_reports_the_true_total(): void
    {
        Asset::factory()->count(15)->create();

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/assets?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(15, $response->json('meta.total'));
    }

    public function test_sort_by_name_desc_actually_reverses_the_order(): void
    {
        Asset::factory()->count(10)->create();

        $asc = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/assets?per_page=50&sort_by=name&sort_dir=asc')
            ->json('data.*.name');
        $desc = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/assets?per_page=50&sort_by=name&sort_dir=desc')
            ->json('data.*.name');

        $this->assertSame($asc, array_reverse($desc));
    }

    public function test_an_unknown_sort_by_does_not_500(): void
    {
        Asset::factory()->count(3)->create();

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/assets?sort_by=name);drop%20table%20assets')
            ->assertOk();
    }

    public function test_search_matches_the_asset_name(): void
    {
        $target = Asset::factory()->create(['name' => 'Zephyr container 900']);
        Asset::factory()->create(['name' => 'Ordinary tank']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/assets?search='.urlencode('Zephyr'));

        $response->assertOk();
        $this->assertSame([$target->id], $response->json('data.*.id'));
    }

    public function test_type_filter_is_applied_server_side(): void
    {
        Asset::factory()->count(4)->create(['asset_type' => 'container']);
        Asset::factory()->count(2)->create(['asset_type' => 'tank']);

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/assets?asset_type=tank&per_page=50');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.total'));
    }
}
