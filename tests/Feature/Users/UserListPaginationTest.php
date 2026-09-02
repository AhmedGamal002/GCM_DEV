<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a real, user-flagged bug: the Users list page
 * used to fetch `per_page=1000` ONCE and let DataTables page/search/sort
 * the whole batch client-side. A tenant with more employees than that
 * (a real one has ~8000) would silently never see the rest at all — not
 * slow, just gone: absent from every page, the search box, and the
 * role/status filter dropdowns (built from whatever was loaded). Fixed
 * by switching to genuine server-side pagination — see
 * resources/assets/js/datatables-server-side.js's docblock.
 */
class UserListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->systemAdmin->assignRole('system_admin');

        User::factory()->count(25)->create()->each(fn (User $u) => $u->assignRole('auditor'));
    }

    public function test_a_small_per_page_still_reports_the_true_total_and_only_returns_that_page(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?per_page=10&page=1');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        // 25 auditors + the admin excluded from the list = 25 visible rows total.
        $this->assertSame(25, $response->json('meta.total'));
    }

    public function test_page_two_returns_the_remaining_rows_not_a_repeat_of_page_one(): void
    {
        $page1 = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?per_page=10&page=1')->json('data');
        $page2 = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?per_page=10&page=2')->json('data');

        $page1Ids = collect($page1)->pluck('id');
        $page2Ids = collect($page2)->pluck('id');

        $this->assertCount(10, $page2);
        $this->assertEmpty($page1Ids->intersect($page2Ids), 'Page 2 repeated rows from page 1.');
    }

    public function test_sort_by_name_desc_actually_reverses_the_order(): void
    {
        $ascNames = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?per_page=50&sort_by=name&sort_dir=asc')
            ->json('data.*.name');
        $descNames = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?per_page=50&sort_by=name&sort_dir=desc')
            ->json('data.*.name');

        $this->assertSame($ascNames, array_reverse($descNames));
    }

    /**
     * `code` is the list's own first column (see Vehicles' analogous
     * plate-search bug, ARCHITECTURE.md §6) — a search box that can't find
     * a row by the value sitting in its own first column is the same
     * class of bug. Was missing entirely: search only matched name/email.
     */
    public function test_searching_by_the_displayed_code_finds_the_user(): void
    {
        $target = User::factory()->create();
        $target->assignRole('auditor');

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?search='.urlencode($target->code));

        $response->assertOk();
        $this->assertSame([$target->id], $response->json('data.*.id'));
    }

    public function test_an_unknown_sort_by_is_ignored_not_a_500(): void
    {
        // Guards the allowlist: a request naming a column that isn't in
        // UserController::SORTABLE_COLUMNS (e.g. a typo, or someone
        // probing for an SQL error / column names) must never reach
        // orderBy() raw — it should just fall back to the default sort.
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users?sort_by=password');

        $response->assertOk();
    }
}
