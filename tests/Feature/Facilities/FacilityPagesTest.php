<?php

namespace Tests\Feature\Facilities;

use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four Blade shells render (routes, breadcrumb, translation objects).
 * The data itself is loaded by the JS from /api/v1/facilities — covered by
 * FacilityManagementTest.
 */
class FacilityPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private IntermediateFacility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        // The page entries only exist in public/build after `npm run build`;
        // these tests are about the Blade shells, not the compiled assets.
        $this->withoutVite();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
        $this->facility = IntermediateFacility::factory()->create();
    }

    public function test_the_list_page_renders_with_the_frd_page_title(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/facility/list')
            ->assertOk()
            ->assertSee('Intermediate Facilities');
    }

    public function test_the_list_offers_create_and_edit_to_a_manager(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/facility/list')
            ->assertOk()
            ->assertSee('can_manage: true', false);
    }

    public function test_the_list_is_read_only_for_the_auditor(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')->get('/app/facility/list')
            ->assertOk()
            ->assertSee('can_manage: false', false);

        $this->actingAs($auditor, 'web')->get("/app/facility/view/{$this->facility->id}")
            ->assertOk()
            ->assertSee('facilityViewCanManage = false', false);
    }

    public function test_the_create_page_renders(): void
    {
        $this->actingAs($this->admin, 'web')->get('/app/facility/add')
            ->assertOk()
            ->assertSee('Create New Facility')
            ->assertSee('id="facilityForm"', false);
    }

    public function test_the_details_and_edit_pages_render(): void
    {
        $this->actingAs($this->admin, 'web')->get("/app/facility/view/{$this->facility->id}")
            ->assertOk()
            ->assertSee('Intermediate Facility Details');

        $this->actingAs($this->admin, 'web')->get("/app/facility/edit/{$this->facility->id}")
            ->assertOk()
            ->assertSee('Edit Facility Details');
    }

    public function test_the_pages_need_a_login(): void
    {
        $this->get('/app/facility/list')->assertRedirect('/login');
    }
}
