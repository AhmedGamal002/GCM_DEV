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

    /** FRD V01.14: auditor gets (عرض/تصدير) على كل الصفحات — sees every built module's menu link now, Users/Drivers included (was blocked from those two under V01.09). */
    public function test_an_auditor_sees_vehicles_users_and_drivers(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $response = $this->actingAs($auditor, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Vehicles<', false);
        $response->assertSee('>Users<', false);
        $response->assertSee('>Drivers<', false);
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

    /**
     * FRD: "(انشاء / تعديل / تعطيل) الحسابات مسؤولية (مدير النظام / مدخل
     * البيانات)" — same line repeated for Users, Drivers, Vehicles and
     * Assets, so data_entry sees all four menu links, same as
     * system_admin (auditor sees all four too now, per the previous test —
     * V01.14 gave it view/export everywhere).
     */
    public function test_a_data_entry_user_sees_users_drivers_vehicles_and_assets(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');

        $response = $this->actingAs($dataEntry, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('>Users<', false);
        $response->assertSee('>Drivers<', false);
        $response->assertSee('>Vehicles<', false);
        $response->assertSee('>Assets<', false);
    }

    /** Vehicle categories management is system_admin only — the "Categories" item under Vehicles. */
    public function test_only_the_system_admin_sees_the_vehicle_categories_menu_item(): void
    {
        $this->actingAs($this->tenantAdmin, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSee('app/vehicle-category/list', false);

        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');
        $this->actingAs($dataEntry, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSee('app/vehicle/list', false)
            ->assertDontSee('app/vehicle-category/list', false);
    }

    public function test_an_auditor_does_not_see_the_vehicle_categories_menu_item(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSee('app/vehicle/list', false)
            ->assertDontSee('app/vehicle-category/list', false);
    }

    /**
     * The sidebar groups the built modules under four section titles:
     * "Accounts" (Users, Drivers), "Fleet & Assets" (Vehicles, Assets),
     * "Operations" (Facilities) and "Clients & Projects" (Client Companies).
     * A title only shows when something under it survives for the role.
     */
    private function headers(User $user): array
    {
        $html = $this->actingAs($user, 'web')->get('/dashboard')->assertOk()->getContent();
        preg_match_all('#<span class="menu-header-text">([^<]+)</span>#', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_system_admin_and_data_entry_see_all_section_titles(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');

        $this->assertSame(['Accounts', 'Fleet & Assets', 'Operations', 'Clients & Projects'], $this->headers($this->tenantAdmin));
        $this->assertSame(['Accounts', 'Fleet & Assets', 'Operations', 'Clients & Projects'], $this->headers($dataEntry));
    }

    /** FRD V01.14: auditor now sees Users/Drivers too, so it gets the "Accounts" header as well — same set as data_entry/system_admin. */
    public function test_an_auditor_sees_all_section_titles_too(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->assertSame(['Accounts', 'Fleet & Assets', 'Operations', 'Clients & Projects'], $this->headers($auditor));
    }

    /** FRD V01.14 §1.8: facilities are (view / export) for the auditor, full for system_admin / data_entry, hidden from drivers. */
    #[\PHPUnit\Framework\Attributes\DataProvider('facilityRolesProvider')]
    public function test_the_facilities_menu_item_shows_for_the_backoffice_roles(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSee('>Facilities<', false)
            ->assertSee('app/facility/list', false);
    }

    public static function facilityRolesProvider(): array
    {
        return [['system_admin'], ['data_entry'], ['auditor']];
    }

    public function test_a_driver_does_not_see_the_facilities_menu_item(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $this->actingAs($driver, 'web')->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>Facilities<', false)
            ->assertDontSee('app/facility/list', false);
    }

    /** The "Add" sub-item under Facilities/Vehicles/Assets matches each module's create ability: system_admin/data_entry only. */
    public function test_only_managers_see_the_add_sub_item_under_facilities_vehicles_and_assets(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        foreach ([$this->tenantAdmin, $dataEntry] as $manager) {
            $this->actingAs($manager, 'web')->get('/dashboard')->assertOk()
                ->assertSee('app/facility/add', false)
                ->assertSee('app/vehicle/add', false)
                ->assertSee('app/asset/add', false);
        }

        $this->actingAs($auditor, 'web')->get('/dashboard')->assertOk()
            ->assertDontSee('app/facility/add', false)
            ->assertDontSee('app/vehicle/add', false)
            ->assertDontSee('app/asset/add', false);
    }

    /** Client Companies (FRD V01.14 §1.11): admin/data_entry manage it, auditor views it, driver never sees it. */
    public function test_client_companies_menu_visibility_per_role(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        foreach ([$this->tenantAdmin, $dataEntry] as $manager) {
            $this->actingAs($manager, 'web')->get('/dashboard')->assertOk()
                ->assertSee('>Client Companies<', false)
                ->assertSee('app/company/list', false)
                ->assertSee('app/company/add', false);
        }

        // the auditor sees the list but not the "Add" link
        $this->actingAs($auditor, 'web')->get('/dashboard')->assertOk()
            ->assertSee('app/company/list', false)
            ->assertDontSee('app/company/add', false);

        $this->actingAs($driver, 'web')->get('/dashboard')->assertOk()
            ->assertDontSee('>Client Companies<', false)
            ->assertDontSee('app/company/list', false);
    }

    /** Client Projects (FRD V01.14 §1.12): same visibility as Client Companies — admin/data_entry manage, auditor views, driver never sees it. */
    public function test_client_projects_menu_visibility_per_role(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        foreach ([$this->tenantAdmin, $dataEntry] as $manager) {
            $this->actingAs($manager, 'web')->get('/dashboard')->assertOk()
                ->assertSee('>Client Projects<', false)
                ->assertSee('app/project/list', false)
                ->assertSee('app/project/add', false);
        }

        $this->actingAs($auditor, 'web')->get('/dashboard')->assertOk()
            ->assertSee('app/project/list', false)
            ->assertDontSee('app/project/add', false);

        $this->actingAs($driver, 'web')->get('/dashboard')->assertOk()
            ->assertDontSee('>Client Projects<', false)
            ->assertDontSee('app/project/list', false);
    }

    public function test_a_driver_sees_no_section_titles_at_all(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $this->assertSame([], $this->headers($driver));
    }

    public function test_the_demo_scaffold_headers_never_show_without_the_demo_switch(): void
    {
        $this->assertNotContains('Apps & Pages', $this->headers($this->tenantAdmin));
        $this->assertNotContains('Components', $this->headers($this->tenantAdmin));
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
     * Client-demo mode: the sidebar shows only what has actually been
     * built — even system_admin no longer sees the Vuexy demo scaffold,
     * but does get the real Dashboard link plus every built module.
     */
    public function test_system_admin_sees_only_the_built_modules_by_default(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')->get('/dashboard');

        $response->assertOk();
        foreach (['Dashboard', 'Users', 'Drivers', 'Vehicles', 'Assets'] as $link) {
            $response->assertSee(">{$link}<", false);
        }
        foreach (['Layouts', 'Front Pages', 'Email', 'Kanban', 'eCommerce', 'Charts', 'Dashboards'] as $demo) {
            $response->assertDontSee(">{$demo}<", false);
        }
    }

    /** SHOW_DEMO_MENU=true (local development only) brings the scaffold back — for system_admin alone. */
    public function test_the_demo_scaffold_can_be_switched_back_on_for_system_admin_only(): void
    {
        config(['custom.custom.showDemoMenu' => true]);

        $this->actingAs($this->tenantAdmin, 'web')->get('/dashboard')
            ->assertOk()
            ->assertSee('>Layouts<', false)
            ->assertSee('>Email<', false);
    }

    public function test_the_demo_scaffold_switch_never_exposes_it_to_other_roles(): void
    {
        config(['custom.custom.showDemoMenu' => true]);

        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');

        $this->actingAs($dataEntry, 'web')->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>Layouts<', false)
            ->assertDontSee('>Email<', false);
    }
}
