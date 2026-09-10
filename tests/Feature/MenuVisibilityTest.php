<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for MenuComposer showing the Platform menu (Tenants,
 * Roles & Permissions) on tenant pages whenever the browser also happens
 * to be authenticated on the `platform` guard — Laravel keeps every
 * guard's login independently in one session, so this is a real,
 * reachable state (e.g. a Super Admin who also logs into a tenant
 * without logging out of Platform first), not just a test artifact.
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantAdmin;

    private PlatformAdmin $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $this->tenantAdmin = User::factory()->create();
        $this->tenantAdmin->assignRole('system_admin');

        $this->platformAdmin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);
    }

    public function test_tenant_page_shows_the_tenant_menu_even_when_also_platform_authenticated(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')
            ->actingAs($this->platformAdmin, 'platform')
            ->get('/app/user/list');

        $response->assertOk();
        $response->assertDontSee('Roles &amp; Permissions', false);
        $response->assertSee('Users', false);
    }

    public function test_platform_page_shows_the_platform_menu_even_when_also_tenant_authenticated(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')
            ->actingAs($this->platformAdmin, 'platform')
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertSee('Tenants', false);
        $response->assertSee('Roles &amp; Permissions', false);
    }

    /**
     * Regression for a real gap: before the menu's "roles" allowlist
     * existed, every tenant-authenticated user saw the exact same sidebar
     * as system_admin — including `driver`, who has zero API access to
     * Users/Drivers/Vehicles (see those Policies). Clicking any of those
     * links loaded a real page whose data call then silently 403'd into
     * what looked like an empty list, not a clear "no access" state.
     */
    public function test_a_driver_does_not_see_users_drivers_or_vehicles_links(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $response = $this->actingAs($driver, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('>Users<', false);
        $response->assertDontSee('>Vehicles<', false);
        $response->assertDontSee('>Drivers<', false);
        $response->assertDontSee('>Assets<', false);
    }

    public function test_an_auditor_sees_vehicles_but_not_users_or_drivers(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $response = $this->actingAs($auditor, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Vehicles<', false);
        $response->assertDontSee('>Users<', false);
        $response->assertDontSee('>Drivers<', false);
    }

    /**
     * Regression for a real gap: the "Assets" menu node (added later, by
     * the Assets module merge) had no "roles" key at all — under
     * MenuComposer's admin-only-by-default rule that made it invisible to
     * auditor/data_entry even though AssetPolicy::viewAny() grants both
     * roles API access. They could reach the API but never see a link to
     * it. User-flagged: "auditor/data_entry permissions aren't right."
     */
    public function test_an_auditor_sees_assets(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $response = $this->actingAs($auditor, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Assets<', false);
    }

    public function test_a_data_entry_user_sees_vehicles_and_assets_but_not_users_or_drivers(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');

        $response = $this->actingAs($dataEntry, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Vehicles<', false);
        $response->assertSee('>Assets<', false);
        $response->assertDontSee('>Users<', false);
        $response->assertDontSee('>Drivers<', false);
    }

    public function test_system_admin_still_sees_all_three_links(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Users<', false);
        $response->assertSee('>Vehicles<', false);
        $response->assertSee('>Drivers<', false);
        $response->assertSee('>Assets<', false);
    }

    /**
     * Second half of the same request ("the menu should only contain what
     * each role is actually allowed to see"): a node with no "roles" key
     * — the entire untouched Vuexy demo scaffold (Layouts, Email, Kanban,
     * Components, ...) — now defaults to system_admin-only instead of
     * "everyone". Every non-admin role should see none of it, but must
     * still land somewhere real: the new top-level "Dashboard" node
     * (roles: data_entry/auditor/driver) covers that.
     */
    public function test_a_driver_sees_no_demo_scaffold_but_does_see_a_dashboard_link(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $response = $this->actingAs($driver, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('>Layouts<', false);
        $response->assertDontSee('>Front Pages<', false);
        $response->assertDontSee('>Email<', false);
        $response->assertDontSee('>Kanban<', false);
        $response->assertSee('>Dashboard<', false);
    }

    public function test_an_auditor_sees_no_demo_scaffold_either(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $response = $this->actingAs($auditor, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('>Layouts<', false);
        $response->assertDontSee('>Email<', false);
        $response->assertSee('>Dashboard<', false);
    }

    /**
     * The explicit "leave admin as-is" half of the request — system_admin
     * must keep seeing the full demo scaffold exactly as before, unchanged.
     */
    public function test_system_admin_still_sees_the_demo_scaffold_unchanged(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Layouts<', false);
        $response->assertSee('>Email<', false);
        $response->assertSee('>Kanban<', false);
    }
}
