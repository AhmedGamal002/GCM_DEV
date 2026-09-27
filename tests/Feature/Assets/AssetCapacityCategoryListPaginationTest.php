<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The capacity-categories list page is server-side processed, same as
 * the assets / vehicles / users lists. The catch: the *same* index
 * endpoint is the unpaginated reference feed for the vehicle & asset
 * form dropdowns — paging only kicks in when `per_page` is sent.
 */
class AssetCapacityCategoryListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
    }

    public function test_without_per_page_the_full_unpaginated_list_is_returned(): void
    {
        AssetCapacityCategory::factory()->count(20)->create();

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/asset-capacity-categories');

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
        // A plain resource collection has no pagination meta.
        $this->assertNull($response->json('meta.total'));
    }

    public function test_a_small_per_page_pages_and_still_reports_the_true_total(): void
    {
        AssetCapacityCategory::factory()->count(20)->create();

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_page_two_does_not_repeat_page_one(): void
    {
        AssetCapacityCategory::factory()->count(20)->create();

        $p1 = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=10&page=1')->json('data.*.id');
        $p2 = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=10&page=2')->json('data.*.id');

        $this->assertEmpty(array_intersect($p1, $p2));
    }

    public function test_sort_by_name_desc_reverses_the_order(): void
    {
        AssetCapacityCategory::factory()->count(10)->create();

        $asc = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=50&sort_by=name&sort_dir=asc')->json('data.*.name');
        $desc = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=50&sort_by=name&sort_dir=desc')->json('data.*.name');

        $this->assertSame($asc, array_reverse($desc));
    }

    public function test_an_unknown_sort_by_does_not_500(): void
    {
        AssetCapacityCategory::factory()->count(3)->create();

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=10&sort_by=name);drop%20table')
            ->assertOk();
    }

    public function test_search_and_applies_to_filters_run_server_side(): void
    {
        AssetCapacityCategory::factory()->create(['name' => 'Zephyr band', 'applies_to' => 'tank']);
        AssetCapacityCategory::factory()->create(['name' => 'Ordinary band', 'applies_to' => 'container']);

        $bySearch = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=10&search=Zephyr')->json('data.*.name');
        $this->assertSame(['Zephyr band'], $bySearch);

        $byType = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?per_page=10&applies_to=container')->json('data.*.name');
        $this->assertSame(['Ordinary band'], $byType);
    }
}
