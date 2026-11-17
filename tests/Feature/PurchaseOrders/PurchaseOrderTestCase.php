<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Quota\DeductQuotaAction;
use App\Models\Company;
use App\Models\Driver;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared setup for the purchase-order core (part 6.1): one tenant, its system
 * admin, a client company "ALN" with a project, and helpers to build POs and
 * charge trips to them (real trip rows since 7.1). The tests call the
 * domain classes directly.
 *
 * "Today" is frozen at 2026-03-01 10:00 (Riyadh), inside the FRD example's
 * PO 1200 (1 Jan – 30 May 2026) — the default range of line().
 */
abstract class PurchaseOrderTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected Company $company;

    protected Project $project;

    protected MainService $service;

    private ?Driver $tripDriver = null;

    private ?Vehicle $tripVehicle = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 3, 1)->setTime(10, 0));

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->company = Company::factory()->create(['prefix' => 'ALN', 'name' => 'Al Nakheel']);
        $this->project = Project::factory()->for($this->company)->create(['name' => 'Riyadh Hotel', 'operational_region' => 'Riyadh']);
        $this->service = MainService::factory()->create(['name' => 'Solid Waste Transport']);
    }

    /** A PO of the project with one line; `$line` overrides the line's attributes. */
    protected function line(array $line = [], string $startsOn = '2026-01-01', string $endsOn = '2026-05-30', ?Project $project = null, array $po = []): PurchaseOrderService
    {
        $order = PurchaseOrder::factory()
            ->forProject($project ?? $this->project)
            ->between($startsOn, $endsOn)
            ->create($po);

        return PurchaseOrderService::factory()->create([
            'purchase_order_id' => $order->id,
            'main_service_id' => $this->service->id,
            ...$line,
        ]);
    }

    /** Charges `$count` trips to the line; returns the trip ids used. */
    protected function charge(PurchaseOrderService $line, int $count = 1, float $tripCost = 50, float $ancillary = 0): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[] = $tripId = $this->newTrip($line);
            app(DeductQuotaAction::class)->execute($line, $tripId, $tripCost, $ancillary, $this->admin);
        }

        return $ids;
    }

    /**
     * A trip row on the line's project and service, NOT charged — the ledger's
     * trip_id is a real foreign key since part 7.1. One driver and one vehicle
     * serve every trip of the test. Returns the trip id.
     */
    protected function newTrip(PurchaseOrderService $line): int
    {
        $this->tripDriver ??= Driver::factory()->create();
        $this->tripVehicle ??= Vehicle::factory()->create();

        return Trip::factory()->onLine($line)->create([
            'driver_id' => $this->tripDriver->id,
            'vehicle_id' => $this->tripVehicle->id,
        ])->id;
    }

    /** A valid create input for CreatePurchaseOrderAction (shape of PurchaseOrderRules::store()). */
    protected function payload(array $overrides = [], array $line = []): array
    {
        return array_merge([
            'paper_number' => '1200',
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-05-30',
            'services' => [[
                'main_service_id' => $this->service->id,
                'quota_method' => 'trips_only',
                'max_trips' => 90,
                'trip_cost' => 100,
                'alert_percent' => 20,
                ...$line,
            ]],
        ], $overrides);
    }
}
