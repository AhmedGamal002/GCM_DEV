<?php

namespace Tests\Feature\Services;

use App\Models\MainService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The eight Blade shells render (routes, breadcrumb with the FRD page
 * titles, translation objects, write buttons hidden from the auditor). The
 * data itself is loaded by the JS from /api/v1/{main,sub}-services —
 * covered by the Management tests. One actor per test (see CLAUDE.md).
 */
class ServicePagesTest extends TestCase
{
    use RefreshDatabase;

    private MainService $main;

    private SubService $sub;

    protected function setUp(): void
    {
        parent::setUp();

        // Page entries only exist in public/build after `npm run build`.
        $this->withoutVite();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->main = MainService::factory()->create();
        $this->sub = SubService::factory()->forService($this->main)->create();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_every_page_renders_with_its_frd_title_for_a_manager(): void
    {
        $this->actingAs($this->actor('system_admin'), 'web');

        $this->get('/app/main-service/list')->assertOk()->assertSee('Main Services')->assertSee('can_manage: true', false);
        $this->get('/app/main-service/add')->assertOk()->assertSee('Create New Main Service')->assertSee('id="mainServiceForm"', false);
        $this->get("/app/main-service/view/{$this->main->id}")->assertOk()->assertSee('Main Service Details')->assertSee('id="msv-edit-link"', false);
        $this->get("/app/main-service/edit/{$this->main->id}")->assertOk()->assertSee('Edit Main Service Details')->assertSee('id="main-service-status-card"', false);

        $this->get('/app/sub-service/list')->assertOk()->assertSee('Sub Services')->assertSee('Create New Sub-service');
        $this->get('/app/sub-service/add')->assertOk()->assertSee('Create New Sub-service')->assertSee('id="subServiceForm"', false)->assertSee('Designated Yard');
        $this->get("/app/sub-service/view/{$this->sub->id}")->assertOk()->assertSee('Service Details')->assertSee('id="ssv-edit-link"', false);
        $this->get("/app/sub-service/edit/{$this->sub->id}")->assertOk()->assertSee('Edit Service Details')->assertSee('id="sub-service-status-card"', false);
    }

    public function test_the_pages_are_read_only_for_the_auditor(): void
    {
        $this->actingAs($this->actor('auditor'), 'web');

        $this->get('/app/main-service/list')->assertOk()->assertSee('can_manage: false', false);
        $this->get("/app/main-service/view/{$this->main->id}")->assertOk()->assertDontSee('id="msv-edit-link"', false);
        $this->get('/app/sub-service/list')->assertOk()->assertSee('can_manage: false', false)->assertDontSee('Create New Sub-service</span>', false);
        $this->get("/app/sub-service/view/{$this->sub->id}")->assertOk()->assertDontSee('id="ssv-edit-link"', false);
    }

    public function test_the_pages_are_translated_in_arabic(): void
    {
        $this->actingAs($this->actor('system_admin'), 'web')->withSession(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->get('/app/main-service/add')->assertOk()->assertSee('إنشاء خدمة أساسية جديدة')->assertSee('نفايات غير خطرة');
        $this->get('/app/sub-service/add')->assertOk()->assertSee('إنشاء خدمة فرعية جديدة')->assertSee('الكود اللوني للحاوية');
    }

    public function test_the_pages_need_a_login(): void
    {
        $this->get('/app/main-service/list')->assertRedirect('/login');
        $this->get('/app/sub-service/list')->assertRedirect('/login');
    }
}
