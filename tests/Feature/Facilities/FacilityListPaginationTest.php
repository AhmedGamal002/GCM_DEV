<?php

namespace Tests\Feature\Facilities;

use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same server-side-processing regression class as AssetListPaginationTest —
 * the list must genuinely paginate, sort, filter and search on the server.
 */
class FacilityListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
    }

    public function test_a_small_per_page_still_reports_the_true_total(): void
    {
        IntermediateFacility::factory()->count(15)->create();

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(15, $response->json('meta.total'));
    }

    public function test_sort_by_name_desc_actually_reverses_the_order(): void
    {
        IntermediateFacility::factory()->count(10)->create();

        $asc = $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=50&sort_by=name&sort_dir=asc')->json('data.*.name');
        $desc = $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=50&sort_by=name&sort_dir=desc')->json('data.*.name');

        $this->assertSame($asc, array_reverse($desc));
    }

    public function test_an_unknown_sort_by_does_not_500(): void
    {
        IntermediateFacility::factory()->count(3)->create();

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/facilities?sort_by=name);drop%20table%20intermediate_facilities')
            ->assertOk();
    }

    public function test_search_matches_the_name_the_prefix_and_the_code(): void
    {
        $byName = IntermediateFacility::factory()->create(['name' => 'Zephyr Landfill', 'prefix' => 'AAA']);
        $byPrefix = IntermediateFacility::factory()->create(['name' => 'Ordinary plant', 'prefix' => 'QXZ']);
        IntermediateFacility::factory()->create(['name' => 'Unrelated', 'prefix' => 'BBB']);

        $this->assertSame([$byName->id], $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?search=Zephyr')->json('data.*.id'));
        $this->assertSame([$byPrefix->id], $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?search=QXZ')->json('data.*.id'));
        // The "ID" column shown in the list — same code the API returns.
        $this->assertSame([$byName->id], $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?search='.$byName->code)->json('data.*.id'));
    }

    public function test_sort_by_code_is_allowed(): void
    {
        IntermediateFacility::factory()->count(5)->create();

        $asc = $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=50&sort_by=code&sort_dir=asc')->json('data.*.code');
        $desc = $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=50&sort_by=code&sort_dir=desc')->json('data.*.code');

        $this->assertSame($asc, array_reverse($desc));
    }

    public function test_the_service_and_status_filters_are_applied_server_side(): void
    {
        IntermediateFacility::factory()->count(3)->disposal()->create();
        IntermediateFacility::factory()->count(2)->recycling()->create();
        IntermediateFacility::factory()->sewage()->deactivated()->create();

        $get = fn (string $qs) => $this->actingAs($this->admin, 'web')->getJson('/api/v1/facilities?per_page=50&'.$qs)->json('meta.total');

        $this->assertSame(2, $get('environmental_service=recycle'));
        $this->assertSame(1, $get('operational_status=deactivated'));
        $this->assertSame(0, $get('environmental_service=recycle&operational_status=deactivated'));
    }
}
