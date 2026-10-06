<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Support\ExpiryWarning;
use App\Models\Driver;
use App\Models\DriverEntryPermit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The "expiring soon" thresholds (General Settings, system admin only) and what they do to the progress
 * bars: every dated driver / vehicle document carries `expiry` — days left, green / red / expired and the
 * label — computed against the driver or vehicle threshold (30 days unless the admin changed it).
 */
class DocumentExpirySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $this->travelTo(now()->setDateTime(2026, 10, 6, 10, 0, 0));

        $this->admin = $this->userWithRole('system_admin');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function inDays(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }

    private function driverWithResidenceIn(int $days): Driver
    {
        $user = $this->userWithRole('driver');

        return Driver::create([
            'user_id' => $user->id,
            'residence_number' => 'RES-1', 'residence_valid_to' => $this->inDays($days),
            'license_number' => 'LIC-1', 'license_valid_to' => $this->inDays(400),
            'operational_license_number' => 'OPL-1', 'operational_license_valid_to' => $this->inDays(400),
            'insurance_number' => 'INS-1', 'insurance_valid_to' => $this->inDays(400),
        ]);
    }

    public function test_the_thresholds_default_to_thirty_days(): void
    {
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/settings/document-expiry')
            ->assertOk()
            ->assertJsonPath('data.driver_expiry_warning_days', 30)
            ->assertJsonPath('data.vehicle_expiry_warning_days', 30);
    }

    public function test_the_system_admin_can_change_them_and_they_stick(): void
    {
        $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', [
            'driver_expiry_warning_days' => 45,
            'vehicle_expiry_warning_days' => 60,
        ])->assertOk()
            ->assertJsonPath('data.driver_expiry_warning_days', 45)
            ->assertJsonPath('data.vehicle_expiry_warning_days', 60);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/settings/document-expiry')
            ->assertJsonPath('data.driver_expiry_warning_days', 45)
            ->assertJsonPath('data.vehicle_expiry_warning_days', 60);

        // Saving again updates the same two rows rather than adding more.
        $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', [
            'driver_expiry_warning_days' => 10,
            'vehicle_expiry_warning_days' => 10,
        ])->assertOk();
        $this->assertSame(2, \App\Models\TenantSetting::count());
    }

    /** @return array<string, array{0: string}> */
    public static function nonAdminRoles(): array
    {
        return ['data entry' => ['data_entry'], 'auditor' => ['auditor'], 'driver' => ['driver']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_the_system_admin_can_read_or_change_them(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user, 'web')->getJson('/api/v1/settings/document-expiry')->assertForbidden();
        $this->actingAs($user, 'web')->patchJson('/api/v1/settings/document-expiry', [
            'driver_expiry_warning_days' => 5,
            'vehicle_expiry_warning_days' => 5,
        ])->assertForbidden();
    }

    public function test_the_days_must_be_a_whole_number_between_one_and_365(): void
    {
        foreach ([0, 366, -3, 'abc', 2.5, ''] as $bad) {
            $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', [
                'driver_expiry_warning_days' => $bad,
                'vehicle_expiry_warning_days' => 30,
            ])->assertUnprocessable()->assertJsonValidationErrors('driver_expiry_warning_days');
        }

        $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_expiry_warning_days', 'vehicle_expiry_warning_days']);
    }

    public function test_the_settings_page_is_for_the_system_admin_only(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin, 'web')->get('/app/settings')->assertOk()->assertSee('driver_expiry_warning_days', false);
        $this->actingAs($this->userWithRole('data_entry'), 'web')->get('/app/settings')->assertForbidden();
    }

    public function test_the_driver_and_vehicle_pages_list_their_documents_in_the_same_kind_of_table_with_a_status_column(): void
    {
        $this->withoutVite();

        foreach (['/app/driver/view/1' => ['dv-docs-table', 'dv-entry-permits-table'], '/app/vehicle/view/1' => ['vv-docs-table', 'vv-permits-table']] as $url => $tables) {
            $html = $this->actingAs($this->admin, 'web')->get($url)->assertOk()->getContent();

            foreach ($tables as $table) {
                $this->assertMatchesRegularExpression('/id="'.$table.'".*?<th>Time left<\/th>\s*<th>Status<\/th>\s*<th>Attachment<\/th>/s', $html, "$table lacks the Time left / Status columns");
            }
        }
    }

    public function test_each_state_has_its_tag_valid_need_review_or_expired(): void
    {
        $warning = app(ExpiryWarning::class);
        $tag = fn (int $days) => $warning->describe(now()->addDays($days), 'driver')['status_label'];

        $this->assertSame('Valid', $tag(31));
        $this->assertSame('Need review', $tag(30));
        $this->assertSame('Need review', $tag(0));
        $this->assertSame('Expired', $tag(-1));

        app()->setLocale('ar');
        $this->assertSame('ساري', $tag(100));
        $this->assertSame('بحاجة إلى مراجعة', $tag(5));
        $this->assertSame('منتهي الصلاحية', $tag(-5));
    }

    public function test_a_document_is_green_until_the_threshold_then_yellow_and_shrinking(): void
    {
        $warning = app(ExpiryWarning::class);
        $on = fn (int $days) => $warning->describe(now()->addDays($days), 'driver');

        $this->assertSame(['ok', 100, 31], [$on(31)['status'], $on(31)['percent'], $on(31)['days_remaining']]);
        $this->assertSame(['warning', 100], [$on(30)['status'], $on(30)['percent']]);
        $this->assertSame(['warning', 50], [$on(15)['status'], $on(15)['percent']]);
        $this->assertSame(['warning', 4], [$on(0)['status'], $on(0)['percent']]);
        $this->assertSame('Expires today', $on(0)['label']);
        $this->assertSame('1 day left', $on(1)['label']);
        $this->assertSame('15 days left', $on(15)['label']);
        $this->assertNull($warning->describe(null, 'driver'));
    }

    public function test_an_expired_document_is_empty_and_says_how_long_ago(): void
    {
        $expiry = app(ExpiryWarning::class)->describe(now()->subDays(3), 'vehicle');

        $this->assertSame(['expired', 0, -3, 'Expired 3 days ago'], [$expiry['status'], $expiry['percent'], $expiry['days_remaining'], $expiry['label']]);
    }

    public function test_the_label_is_in_arabic_in_the_arabic_locale(): void
    {
        app()->setLocale('ar');
        $warning = app(ExpiryWarning::class);

        $this->assertSame('متبقٍّ يومان', $warning->describe(now()->addDays(2), 'driver')['label']);
        $this->assertSame('متبقٍّ 5 أيام', $warning->describe(now()->addDays(5), 'driver')['label']);
        $this->assertSame('متبقٍّ 20 يومًا', $warning->describe(now()->addDays(20), 'driver')['label']);
        $this->assertSame('انتهت الصلاحية منذ 4 أيام', $warning->describe(now()->subDays(4), 'driver')['label']);
    }

    public function test_the_driver_record_carries_the_expiry_of_every_dated_document_and_permit(): void
    {
        $driver = $this->driverWithResidenceIn(12);
        DriverEntryPermit::create(['driver_id' => $driver->id, 'area_name' => 'Riyadh', 'permit_number' => 'P-1', 'valid_to' => $this->inDays(100)]);

        $data = $this->actingAs($this->admin, 'web')->getJson("/api/v1/drivers/{$driver->id}")->assertOk()->json('data');

        $this->assertSame('warning', $data['residence']['expiry']['status']);
        $this->assertSame(12, $data['residence']['expiry']['days_remaining']);
        $this->assertSame('ok', $data['license']['expiry']['status']);
        $this->assertSame('ok', $data['entry_permits'][0]['expiry']['status']);
    }

    public function test_changing_the_driver_threshold_changes_the_drivers_bars_but_not_the_vehicles(): void
    {
        $driver = $this->driverWithResidenceIn(45);
        $vehicle = Vehicle::factory()->create();
        VehicleDocument::factory()->create(['vehicle_id' => $vehicle->id, 'type' => 'insurance', 'valid_to' => $this->inDays(45)]);

        $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', [
            'driver_expiry_warning_days' => 60,
            'vehicle_expiry_warning_days' => 30,
        ])->assertOk();

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/drivers/{$driver->id}")
            ->assertJsonPath('data.residence.expiry.status', 'warning')
            ->assertJsonPath('data.residence.expiry.percent', 75);

        $documents = $this->actingAs($this->admin, 'web')->getJson("/api/v1/vehicles/{$vehicle->id}")->json('data.documents');
        $insurance = collect($documents)->firstWhere('type', 'insurance');
        $this->assertSame('ok', $insurance['expiry']['status']);
    }
}
