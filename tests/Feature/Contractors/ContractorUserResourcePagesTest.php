<?php

namespace Tests\Feature\Contractors;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleDocument;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD V01.14 §6 "توصيف صفحات النظام لدور (مستخدمي المتعهد)": a contractor's
 * account reads — and exports — its own contractor's drivers, contractor users,
 * vehicles and containers, and nothing else: no other contractor's, none of
 * GCM's own, no client accounts. It never writes. The rule is one scope
 * (VisibleToContractorAccount / User::scopeVisibleTo) and one per-record
 * check in each policy; these tests walk every endpoint that reaches it.
 */
class ContractorUserResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Contractor $desert;

    private Contractor $gulf;

    private User $actor;

    private User $colleague;

    private User $desertDriver;

    private User $gulfDriver;

    private User $gcmDriver;

    private User $gulfUser;

    private User $clientUser;

    private Vehicle $desertVehicle;

    private Vehicle $gulfVehicle;

    private Vehicle $gcmVehicle;

    private Asset $desertAsset;

    private Asset $gulfAsset;

    private Asset $gcmAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->desert = Contractor::factory()->create(['name' => 'Desert Haulage', 'prefix' => 'DSH']);
        $this->gulf = Contractor::factory()->create(['name' => 'Gulf Logistics', 'prefix' => 'GLG']);

        $this->actor = User::factory()->contractorUser($this->desert)->create(['name' => 'Omar Actor', 'phone' => '+966500000401']);
        $this->colleague = User::factory()->contractorUser($this->desert)->create(['name' => 'Desert Colleague', 'phone' => '+966500000402']);
        $this->gulfUser = User::factory()->contractorUser($this->gulf)->create(['name' => 'Gulf Person', 'phone' => '+966500000403']);

        $this->desertDriver = $this->makeDriver('Desert Driver', $this->desert);
        $this->gulfDriver = $this->makeDriver('Gulf Driver', $this->gulf);
        $this->gcmDriver = $this->makeDriver('GCM Driver', null);

        $this->clientUser = User::factory()->client()->create(['name' => 'Client Boss', 'phone' => '+966500000404']);

        $staff = User::factory()->create(['name' => 'Staff Admin']);
        $staff->assignRole('system_admin');

        $this->desertVehicle = $this->makeVehicle('DSH', $this->desert);
        $this->gulfVehicle = $this->makeVehicle('GLG', $this->gulf);
        $this->gcmVehicle = $this->makeVehicle('GCM', null);

        AssetCapacityCategory::factory()->container()->create();
        $this->desertAsset = $this->makeAsset('Desert box', $this->desert);
        $this->gulfAsset = $this->makeAsset('Gulf box', $this->gulf);
        $this->gcmAsset = $this->makeAsset('GCM box', null);
    }

    private function makeDriver(string $name, ?Contractor $contractor): User
    {
        $factory = User::factory();
        $user = ($contractor
            ? $factory->state(['affiliation' => 'contractor', 'contractor_id' => $contractor->id])
            : $factory)->create(['name' => $name, 'phone' => '+9665000'.random_int(10000, 99999)]);
        $user->assignRole('driver');

        Driver::create([
            'user_id' => $user->id,
            'contractor_id' => $contractor?->id,
            'residence_number' => 'RES-1', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-1', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL-1', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-1', 'insurance_valid_to' => '2030-01-01',
        ]);

        return $user;
    }

    private function makeVehicle(string $letters, ?Contractor $contractor): Vehicle
    {
        return Vehicle::factory()->create([
            'plate_letters' => $letters,
            'plate_numbers' => (string) random_int(1000, 9999),
            'vehicle_category_id' => VehicleCategory::where('slug', 'hook_lift')->value('id'),
            'affiliation' => $contractor ? 'contractor' : 'gcm',
            'contractor_id' => $contractor?->id,
        ]);
    }

    private function makeAsset(string $name, ?Contractor $contractor): Asset
    {
        return Asset::factory()->container()->create([
            'name' => $name,
            'affiliation' => $contractor ? 'contractor' : 'gcm',
            'contractor_id' => $contractor?->id,
        ]);
    }

    private function asActor()
    {
        return $this->actingAs($this->actor, 'web');
    }

    // ------------------------------------------------------------------ users

    public function test_the_users_list_holds_only_its_contractors_drivers_and_users(): void
    {
        $names = collect($this->asActor()->getJson('/api/v1/users?per_page=100')->assertOk()->json('data'))->pluck('name');

        // Its colleague and its driver — not itself, not another contractor's people, not GCM's, not a client's.
        $this->assertEqualsCanonicalizing(['Desert Colleague', 'Desert Driver'], $names->all());
    }

    public function test_the_users_list_filters_by_role_inside_that_scope(): void
    {
        $drivers = collect($this->asActor()->getJson('/api/v1/users?role=driver')->json('data'))->pluck('name')->all();
        $this->assertSame(['Desert Driver'], $drivers);

        // A filter pointing at someone else's contractor cannot widen the scope.
        $widened = collect($this->asActor()->getJson('/api/v1/users?contractor_id='.$this->gulf->id)->json('data'));
        $this->assertCount(0, $widened);
    }

    public function test_it_opens_its_own_drivers_and_colleagues_only(): void
    {
        $this->asActor()->getJson("/api/v1/users/{$this->desertDriver->id}")->assertOk();
        $this->asActor()->getJson("/api/v1/users/{$this->colleague->id}")->assertOk();

        foreach ([$this->gulfDriver, $this->gulfUser, $this->gcmDriver, $this->clientUser] as $other) {
            $this->asActor()->getJson("/api/v1/users/{$other->id}")->assertForbidden();
        }
    }

    public function test_the_users_export_holds_the_same_rows(): void
    {
        $this->asActor()->get('/api/v1/users/export?format=xlsx')->assertOk();
        $this->asActor()->get('/api/v1/users/export?format=pdf')->assertOk();
    }

    // ----------------------------------------------------------------- drivers

    public function test_the_drivers_list_stats_and_export_are_its_own(): void
    {
        $rows = collect($this->asActor()->getJson('/api/v1/drivers?per_page=100')->assertOk()->json('data'));
        $this->assertSame(['Desert Driver'], $rows->pluck('name')->all());

        $stats = $this->asActor()->getJson('/api/v1/drivers/stats')->assertOk()->json('data');
        $this->assertSame(1, $stats['available']);

        $this->asActor()->get('/api/v1/drivers/export?format=xlsx')->assertOk();
    }

    public function test_it_opens_its_own_driver_but_not_another_contractors_or_gcms(): void
    {
        $own = Driver::where('user_id', $this->desertDriver->id)->firstOrFail();
        $this->asActor()->getJson("/api/v1/drivers/{$own->id}")->assertOk();

        foreach ([$this->gulfDriver, $this->gcmDriver] as $other) {
            $driver = Driver::where('user_id', $other->id)->firstOrFail();
            $this->asActor()->getJson("/api/v1/drivers/{$driver->id}")->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- vehicles

    public function test_the_vehicles_list_stats_and_export_are_its_own(): void
    {
        $plates = collect($this->asActor()->getJson('/api/v1/vehicles?per_page=100')->assertOk()->json('data'))->pluck('plate_letters');
        $this->assertSame(['DSH'], $plates->all());

        $stats = $this->asActor()->getJson('/api/v1/vehicles/stats')->assertOk()->json('data');
        $this->assertSame(1, $stats['availability']['available']);
        $this->assertSame(1, collect($stats['by_category'])->sum('count'));

        $this->asActor()->get('/api/v1/vehicles/export?format=xlsx')->assertOk();
        $this->asActor()->get('/api/v1/vehicles/export?format=pdf')->assertOk();
    }

    public function test_it_opens_its_own_vehicle_but_not_another_contractors_or_gcms(): void
    {
        $this->asActor()->getJson("/api/v1/vehicles/{$this->desertVehicle->id}")->assertOk();

        $this->asActor()->getJson("/api/v1/vehicles/{$this->gulfVehicle->id}")->assertForbidden();
        $this->asActor()->getJson("/api/v1/vehicles/{$this->gcmVehicle->id}")->assertForbidden();
    }

    // ------------------------------------------------------------------ assets

    public function test_the_assets_list_stats_and_export_are_its_own(): void
    {
        $names = collect($this->asActor()->getJson('/api/v1/assets?per_page=100')->assertOk()->json('data'))->pluck('name');
        $this->assertSame(['Desert box'], $names->all());

        $stats = $this->asActor()->getJson('/api/v1/assets/stats')->assertOk()->json('data');
        $this->assertSame(1, $stats['container']['available']);

        $this->asActor()->get('/api/v1/assets/export?format=xlsx')->assertOk();
    }

    public function test_it_opens_its_own_asset_but_not_another_contractors_or_gcms(): void
    {
        $this->asActor()->getJson("/api/v1/assets/{$this->desertAsset->id}")->assertOk();

        $this->asActor()->getJson("/api/v1/assets/{$this->gulfAsset->id}")->assertForbidden();
        $this->asActor()->getJson("/api/v1/assets/{$this->gcmAsset->id}")->assertForbidden();
    }

    // --------------------------------------------------------------- documents

    public function test_a_document_download_re_checks_the_same_rule(): void
    {
        // Another contractor's / GCM's driver: refused before any file is looked up.
        foreach ([$this->gulfDriver, $this->gcmDriver] as $other) {
            $driver = Driver::where('user_id', $other->id)->firstOrFail();
            $this->asActor()->get("/api/v1/drivers/{$driver->id}/documents/license")->assertForbidden();
        }

        // Its own driver passes the policy (no file uploaded -> 404, not 403).
        $own = Driver::where('user_id', $this->desertDriver->id)->firstOrFail();
        $this->asActor()->get("/api/v1/drivers/{$own->id}/documents/license")->assertNotFound();

        $gulfDoc = VehicleDocument::factory()->create(['vehicle_id' => $this->gulfVehicle->id]);
        $this->asActor()->get("/api/v1/vehicles/{$this->gulfVehicle->id}/documents/{$gulfDoc->id}/download")->assertForbidden();
    }

    // ------------------------------------------------------------------ writes

    public function test_it_never_writes_anything(): void
    {
        $own = Driver::where('user_id', $this->desertDriver->id)->firstOrFail();

        $this->asActor()->postJson('/api/v1/vehicles', [])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/vehicles/{$this->desertVehicle->id}", [])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/vehicles/{$this->desertVehicle->id}/status", ['status' => 'deactivated'])->assertForbidden();

        $this->asActor()->postJson('/api/v1/assets', [])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/assets/{$this->desertAsset->id}", ['name' => 'x'])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/assets/{$this->desertAsset->id}/status", ['status' => 'deactivated'])->assertForbidden();

        $this->asActor()->postJson('/api/v1/drivers', [])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/drivers/{$own->id}", [])->assertForbidden();

        $this->asActor()->postJson('/api/v1/users', [])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/users/{$this->desertDriver->id}/status", ['status' => 'deactivated'])->assertForbidden();
        $this->asActor()->patchJson("/api/v1/users/{$this->colleague->id}", ['name' => 'x'])->assertForbidden();

        $this->assertSame('active', $this->desertDriver->fresh()->status);
    }

    public function test_a_contractor_account_with_no_contractor_sees_nothing(): void
    {
        $this->actor->forceFill(['contractor_id' => null])->save();

        $this->asActor()->getJson('/api/v1/vehicles')->assertOk()->assertJsonCount(0, 'data');
        $this->asActor()->getJson('/api/v1/assets')->assertOk()->assertJsonCount(0, 'data');
        $this->asActor()->getJson('/api/v1/drivers')->assertOk()->assertJsonCount(0, 'data');
        $this->asActor()->getJson('/api/v1/users')->assertOk()->assertJsonCount(0, 'data');
        $this->asActor()->getJson("/api/v1/vehicles/{$this->desertVehicle->id}")->assertForbidden();
    }

    /** Everyone else keeps seeing the whole tenant (the scope only narrows the contractor's own role). */
    public function test_staff_still_see_every_contractors_resources(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->assertCount(3, $this->actingAs($auditor, 'web')->getJson('/api/v1/vehicles?per_page=100')->json('data'));
        $this->assertCount(3, $this->actingAs($auditor, 'web')->getJson('/api/v1/assets?per_page=100')->json('data'));
    }

    // ------------------------------------------------------------------- pages

    public function test_the_read_pages_open_and_the_form_pages_do_not(): void
    {
        $this->withoutVite();

        foreach (['/app/user/list', '/app/driver/list', '/app/vehicle/list', '/app/asset/list'] as $url) {
            $this->asActor()->get($url)->assertOk();
        }

        foreach (['/app/user/add', '/app/driver/add', '/app/vehicle/add', '/app/asset/add', '/app/asset/insert-into-project'] as $url) {
            $this->asActor()->get($url)->assertForbidden();
        }
    }

    public function test_the_pages_are_view_only_for_the_role(): void
    {
        $this->withoutVite();

        $this->asActor()->get('/app/vehicle/list')->assertOk()->assertSee('data-gcm-view-only="1"', false);
    }

    /** The affiliation / company filters mean nothing inside one contractor's own records — hidden, and not requested. */
    public function test_the_filters_that_do_not_apply_are_hidden(): void
    {
        $this->withoutVite();

        $users = $this->asActor()->get('/app/user/list')->assertOk()->getContent();
        $this->assertStringContainsString('contractor_scoped: true', $users);
        $this->assertMatchesRegularExpression('/user_affiliation\s+d-none/', $users);
        $this->assertMatchesRegularExpression('/user_company\s+d-none/', $users);

        foreach (['vehicle' => '/app/vehicle/list', 'asset' => '/app/asset/list', 'driver' => '/app/driver/list'] as $name => $url) {
            $html = $this->asActor()->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression("/{$name}_affiliation\\s+d-none/", $html);
        }

        // ... and an admin's keeps every filter.
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        $html = $this->actingAs($admin, 'web')->get('/app/user/list')->assertOk()->getContent();
        $this->assertStringContainsString('contractor_scoped: false', $html);
        $this->assertDoesNotMatchRegularExpression('/user_affiliation\s+d-none/', $html);
    }
}
