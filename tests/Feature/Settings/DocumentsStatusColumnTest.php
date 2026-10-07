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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The "Documents Status" column of the driver and vehicle lists (and exports): one tag per row from all of
 * its dated documents and permits — any in yellow → Need review; all expired → Expired; all valid → Valid;
 * (expired next to valid, none yellow → Need review).
 */
class DocumentsStatusColumnTest extends TestCase
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
    }

    private function inDays(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }

    /** @return array<string, array{0: list<int>, 1: ?string}> days-from-today of each document => expected status */
    public static function cases(): array
    {
        return [
            'all valid' => [[100, 200, 365], 'ok'],
            'one about to expire' => [[100, 20, 365], 'warning'],
            'on the threshold itself' => [[100, 30], 'warning'],
            'all expired' => [[-1, -30], 'expired'],
            'one expired, rest valid' => [[-1, 100], 'warning'],
            'one expired, one yellow, one valid' => [[-1, 10, 100], 'warning'],
            'no dated document' => [[], null],
        ];
    }

    /** @param  list<int>  $days */
    #[DataProvider('cases')]
    public function test_the_overall_status_rules(array $days, ?string $expected): void
    {
        $dates = array_map(fn (int $d) => now()->addDays($d), $days);

        $overall = app(ExpiryWarning::class)->overall($dates, ExpiryWarning::SCOPE_DRIVER);

        $this->assertSame($expected, $overall['status'] ?? null);
    }

    public function test_the_labels_are_valid_need_review_and_expired_in_both_languages(): void
    {
        $warning = app(ExpiryWarning::class);
        $label = fn (array $days) => $warning->overall(array_map(fn ($d) => now()->addDays($d), $days), 'driver')['status_label'];

        $this->assertSame(['Valid', 'Need review', 'Expired'], [$label([90]), $label([5]), $label([-5])]);

        app()->setLocale('ar');
        $this->assertSame(['ساري', 'بحاجة إلى مراجعة', 'منتهي الصلاحية'], [$label([90]), $label([5]), $label([-5])]);
    }

    private function driver(int $residenceDays, ?int $permitDays = null): Driver
    {
        $user = User::factory()->create();
        $user->assignRole('driver');

        $driver = Driver::create([
            'user_id' => $user->id,
            'residence_number' => 'R', 'residence_valid_to' => $this->inDays($residenceDays),
            'license_number' => 'L', 'license_valid_to' => $this->inDays(400),
            'operational_license_number' => 'O', 'operational_license_valid_to' => $this->inDays(400),
            'insurance_number' => 'I', 'insurance_valid_to' => $this->inDays(400),
        ]);

        if ($permitDays !== null) {
            DriverEntryPermit::create(['driver_id' => $driver->id, 'area_name' => 'Riyadh', 'permit_number' => 'P', 'valid_to' => $this->inDays($permitDays)]);
        }

        return $driver;
    }

    public function test_the_driver_list_rows_carry_the_status_and_count_the_entry_permits_too(): void
    {
        $valid = $this->driver(100);
        $yellow = $this->driver(10);
        $expired = $this->driver(-5);
        $permitDue = $this->driver(100, 7);

        $rows = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?per_page=50')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('ok', $rows[$valid->id]['documents_status']['status']);
        $this->assertSame('Valid', $rows[$valid->id]['documents_status']['status_label']);
        $this->assertSame('warning', $rows[$yellow->id]['documents_status']['status']);
        $this->assertSame('Need review', $rows[$yellow->id]['documents_status']['status_label']);
        // One of four documents expired while the others are valid → needs review, not "Valid".
        $this->assertSame('warning', $rows[$expired->id]['documents_status']['status']);
        // All documents valid but an entry permit is about to expire.
        $this->assertSame('warning', $rows[$permitDue->id]['documents_status']['status']);
    }

    public function test_the_vehicle_list_rows_carry_the_status(): void
    {
        $valid = Vehicle::factory()->create();
        VehicleDocument::factory()->create(['vehicle_id' => $valid->id, 'type' => 'insurance', 'valid_to' => $this->inDays(200)]);

        $expired = Vehicle::factory()->create();
        VehicleDocument::factory()->create(['vehicle_id' => $expired->id, 'type' => 'insurance', 'valid_to' => $this->inDays(-3)]);
        VehicleDocument::factory()->entryPermit()->create(['vehicle_id' => $expired->id, 'valid_to' => $this->inDays(-10)]);

        $yellowPermit = Vehicle::factory()->create();
        VehicleDocument::factory()->create(['vehicle_id' => $yellowPermit->id, 'type' => 'insurance', 'valid_to' => $this->inDays(200)]);
        VehicleDocument::factory()->entryPermit()->create(['vehicle_id' => $yellowPermit->id, 'valid_to' => $this->inDays(9)]);

        $rows = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles?per_page=50')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('ok', $rows[$valid->id]['documents_status']['status']);
        $this->assertSame('expired', $rows[$expired->id]['documents_status']['status']);
        $this->assertSame('Expired', $rows[$expired->id]['documents_status']['status_label']);
        $this->assertSame('warning', $rows[$yellowPermit->id]['documents_status']['status']);
    }

    public function test_the_list_does_not_run_a_query_per_row(): void
    {
        foreach (range(1, 6) as $i) {
            $this->driver(100, 50);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?per_page=50')->assertOk();
        $permitQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'driver_entry_permits'))->count();

        $this->assertSame(1, $permitQueries, 'entry permits must be eager-loaded, not fetched per driver');
    }

    public function test_the_list_pages_have_the_documents_status_column_and_filter(): void
    {
        $this->withoutVite();

        foreach (['/app/driver/list' => 'driver_documents', '/app/vehicle/list' => 'vehicle_documents'] as $url => $slot) {
            $this->actingAs($this->admin, 'web')->get($url)->assertOk()
                ->assertSee('<th>Documents Status</th>', false)
                ->assertSee($slot, false);
        }
    }

    /** The scenarios the SQL filter must agree with the tag on — boundaries included (today, +30, +31, -1). */
    private const SCENARIOS = [
        'valid' => [100, 365, 400],
        'valid, just past the threshold' => [31, 200],
        'yellow on the threshold' => [30, 200],
        'yellow today' => [0, 200],
        'yellow, rest valid' => [12, 200, 300],
        'expired yesterday, all expired' => [-1, -40],
        'one expired, rest valid' => [-1, 200],
        'one expired, one yellow, rest valid' => [-1, 10, 200],
        'all yellow' => [5, 6],
    ];

    public function test_the_driver_filter_returns_exactly_the_rows_the_tag_says(): void
    {
        $byScenario = [];
        foreach (self::SCENARIOS as $name => $days) {
            // Four document dates + permits for anything beyond the fourth.
            $docs = array_pad($days, 4, $days[0]);
            $driver = $this->driverWithDays(array_slice($docs, 0, 4));
            foreach (array_slice($days, 4) as $permit) {
                DriverEntryPermit::create(['driver_id' => $driver->id, 'area_name' => 'A', 'permit_number' => 'P', 'valid_to' => $this->inDays($permit)]);
            }
            $byScenario[$name] = $driver;
        }
        // A driver whose documents are all valid but who has an expired permit next to them.
        $byScenario['valid docs + expired permit'] = $this->driver(100, -2);

        $listed = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?per_page=100')->json('data'))->keyBy('id');

        foreach (ExpiryWarning::STATUSES as $status) {
            $expected = $listed->filter(fn ($row) => ($row['documents_status']['status'] ?? null) === $status)->keys()->sort()->values()->all();
            $filtered = collect($this->actingAs($this->admin, 'web')->getJson("/api/v1/drivers?per_page=100&documents_status={$status}")->assertOk()->json('data'))
                ->pluck('id')->sort()->values()->all();

            $this->assertNotEmpty($expected, "no scenario produced '{$status}'");
            $this->assertSame($expected, $filtered, "filter '{$status}' disagrees with the row tags");
        }
    }

    public function test_the_vehicle_filter_returns_exactly_the_rows_the_tag_says(): void
    {
        foreach (self::SCENARIOS as $days) {
            $vehicle = Vehicle::factory()->create();
            foreach ($days as $i => $d) {
                $i % 2 === 0
                    ? VehicleDocument::factory()->create(['vehicle_id' => $vehicle->id, 'type' => 'insurance', 'valid_to' => $this->inDays($d)])
                    : VehicleDocument::factory()->entryPermit()->create(['vehicle_id' => $vehicle->id, 'valid_to' => $this->inDays($d)]);
            }
        }
        Vehicle::factory()->create(); // no documents at all → in no filter

        $listed = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles?per_page=100')->json('data'))->keyBy('id');
        $this->assertNull($listed->first(fn ($r) => $r['documents'] === [])['documents_status'] ?? null);

        foreach (ExpiryWarning::STATUSES as $status) {
            $expected = $listed->filter(fn ($row) => ($row['documents_status']['status'] ?? null) === $status)->keys()->sort()->values()->all();
            $filtered = collect($this->actingAs($this->admin, 'web')->getJson("/api/v1/vehicles?per_page=100&documents_status={$status}")->assertOk()->json('data'))
                ->pluck('id')->sort()->values()->all();

            $this->assertNotEmpty($expected, "no scenario produced '{$status}'");
            $this->assertSame($expected, $filtered, "filter '{$status}' disagrees with the row tags");
        }
    }

    public function test_the_filter_follows_the_warning_period_setting_and_an_unknown_value_is_ignored(): void
    {
        $this->driver(45);   // yellow only once the driver threshold is 60
        $all = $this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?per_page=50')->json('meta.total');

        $this->assertSame(0, $this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?documents_status=warning')->json('meta.total') ?? 0);
        $this->actingAs($this->admin, 'web')->patchJson('/api/v1/settings/document-expiry', ['driver_expiry_warning_days' => 60, 'vehicle_expiry_warning_days' => 30])->assertOk();
        $this->assertSame(1, $this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?documents_status=warning')->json('meta.total'));

        // Anything but ok / warning / expired is not a filter.
        $this->assertSame($all, $this->actingAs($this->admin, 'web')->getJson('/api/v1/drivers?documents_status=nonsense')->json('meta.total'));
    }

    public function test_the_exports_read_the_same_filter(): void
    {
        $this->driver(100);
        $this->driver(-5);

        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->actingAs($this->admin, 'web')->get('/api/v1/drivers/export?format=xlsx&documents_status=ok')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('drivers.xlsx', function ($export) {
            return $export->collection()->count() === 1;
        });
    }

    /** @param  list<int>  $days  residence, license, operational license, insurance */
    private function driverWithDays(array $days): Driver
    {
        $user = User::factory()->create();
        $user->assignRole('driver');

        return Driver::create([
            'user_id' => $user->id,
            'residence_number' => 'R', 'residence_valid_to' => $this->inDays($days[0]),
            'license_number' => 'L', 'license_valid_to' => $this->inDays($days[1]),
            'operational_license_number' => 'O', 'operational_license_valid_to' => $this->inDays($days[2]),
            'insurance_number' => 'I', 'insurance_valid_to' => $this->inDays($days[3]),
        ]);
    }
}
