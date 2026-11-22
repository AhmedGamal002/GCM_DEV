<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\CancelTripAction;
use App\Domain\Trips\Actions\ClientApproveTripAction;
use App\Domain\Trips\Actions\ClientRejectTripAction;
use App\Domain\Trips\Actions\CreateTripByDriverAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Domain\Trips\Actions\FinalApproveTripAction;
use App\Domain\Trips\Options\TripResourceAvailability;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Company;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Shared setup for the trip core (part 7.1) — there is no API yet (7.4), so
 * the tests drive the actions directly:
 *
 *  - one tenant with its system admin, data entry and auditor;
 *  - client company "ALN" with the project "Riyadh Hotel" whose
 *    representative is the client's project manager (sees every project),
 *    plus a project auditor of the same company;
 *  - the main service "Solid Waste Transport" with two sub-services, both
 *    served by one disposal facility — "General collection" and "Cardboard
 *    collection" (which requires a recycle receipt);
 *  - PO ALN-1200 on the project (1 Jan – 30 May 2026): 90 trips × 100 SAR;
 *  - a GCM driver, a vehicle, and two 12 m³ / 3.75 t containers (going and
 *    returning; the returning one sits at the project).
 *
 * "Today" is 2026-03-01 10:00 (Riyadh). Files go to a fake private disk.
 */
abstract class TripTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $dataEntry;

    protected User $auditor;

    protected Company $company;

    protected Project $project;

    protected User $clientManager;

    protected User $clientAuditor;

    protected MainService $service;

    protected SubService $sub;

    protected SubService $receiptSub;

    protected IntermediateFacility $facility;

    protected PurchaseOrder $po;

    protected PurchaseOrderService $line;

    protected Driver $driver;

    protected Vehicle $vehicle;

    protected AssetCapacityCategory $capacity;

    protected Asset $goingAsset;

    protected Asset $returningAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 3, 1)->setTime(10, 0));
        Storage::fake('local');

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = $this->staff('system_admin', 'admin@gcm.test');
        $this->dataEntry = $this->staff('data_entry', 'entry@gcm.test');
        $this->auditor = $this->staff('auditor', 'auditor@gcm.test');

        $this->company = Company::factory()->create(['prefix' => 'ALN', 'name' => 'Al Nakheel']);
        $this->project = Project::factory()->for($this->company)->create(['name' => 'Riyadh Hotel', 'operational_region' => 'Riyadh']);
        $this->clientManager = User::factory()->client($this->company, 'client_project_manager')->create(['name' => 'Client Manager']);
        $this->clientAuditor = User::factory()->client($this->company, 'client_project_auditor')->create(['name' => 'Client Auditor']);
        $this->project->forceFill(['representative_id' => $this->clientManager->id])->save();

        $this->service = MainService::factory()->create(['name' => 'Solid Waste Transport', 'service_type' => 'non_hazardous']);
        $this->facility = IntermediateFacility::factory()->create(['name' => 'Riyadh Landfill']);
        $this->sub = SubService::factory()->forService($this->service)->create(['name' => 'General collection']);
        $this->receiptSub = SubService::factory()->forService($this->service)->create(['name' => 'Cardboard collection', 'requires_recycle_receipt' => true]);
        $this->sub->facilities()->attach($this->facility);
        $this->receiptSub->facilities()->attach($this->facility);

        $this->po = PurchaseOrder::factory()->forProject($this->project)->between('2026-01-01', '2026-05-30')->create(['number' => 'ALN-1200']);
        $this->line = PurchaseOrderService::factory()->create([
            'purchase_order_id' => $this->po->id,
            'main_service_id' => $this->service->id,
            'max_trips' => 90,
            'trip_cost' => 100,
        ]);

        $this->driver = Driver::factory()->create();
        $this->driver->user->forceFill(['name' => 'Driver One'])->save();
        $this->vehicle = Vehicle::factory()->create(['plate_letters' => 'ABC', 'plate_numbers' => '1234']);
        $this->capacity = AssetCapacityCategory::factory()->create(['name' => '12 m3', 'applies_to' => 'container', 'capacity_cbm' => 12, 'capacity_ton' => 3.75]);
        $this->goingAsset = Asset::factory()->container()->create(['name' => 'C-GO', 'asset_capacity_category_id' => $this->capacity->id]);
        $this->returningAsset = Asset::factory()->container()->create(['name' => 'C-BACK', 'asset_capacity_category_id' => $this->capacity->id]);
        $this->returningAsset->forceFill(['project_id' => $this->project->id])->save();

        // The cascading choices (7.2) are re-checked on every save: make the fixture hang together.
        $this->makeCompatible($this->driver, $this->vehicle, $this->service, [$this->sub, $this->receiptSub], [$this->goingAsset, $this->returningAsset]);
    }

    /**
     * Ties a trip's resources the way the trip form requires (TripCompatibility): the driver is
     * qualified for the vehicle's category, the main service supports it, the containers fit it, and
     * the sub-services take the containers' type and capacity.
     *
     * @param  list<SubService>  $subs
     * @param  list<Asset>  $assets
     */
    protected function makeCompatible(?Driver $driver, Vehicle $vehicle, ?MainService $service = null, array $subs = [], array $assets = []): void
    {
        $driver?->qualifiedVehicleCategories()->syncWithoutDetaching([$vehicle->vehicle_category_id]);
        $service?->vehicleCategories()->syncWithoutDetaching([$vehicle->vehicle_category_id]);

        foreach ($assets as $asset) {
            $asset->compatibleVehicleCategories()->syncWithoutDetaching([$vehicle->vehicle_category_id]);

            foreach ($subs as $sub) {
                $sub->capacities()->syncWithoutDetaching([$asset->asset_capacity_category_id]);
            }
        }

        if ($vehicle->has_embedded_container) {
            foreach ($subs as $sub) {
                $sub->capacities()->syncWithoutDetaching([$vehicle->embedded_asset_capacity_category_id]);
            }
        }
    }

    protected function staff(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => ucfirst(str_replace('_', ' ', $role))]);
        $user->assignRole($role);

        return $user;
    }

    /** A complete staff create input; `$overrides` replace keys. */
    protected function staffData(array $overrides = []): array
    {
        return array_merge([
            'trip_at' => '2026-03-01 09:30',
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            ...$this->containers(going: true),
            'requires_client_approval' => true,
            'requires_purchase_order' => true,
            'purchase_order_id' => $this->po->id,
            'main_service_id' => $this->service->id,
            'sub_service_id' => $this->sub->id,
            'intermediate_facility_id' => $this->facility->id,
        ], $overrides);
    }

    /**
     * The fixture's containers — or, once an unfinished trip holds them (a trip reserves its
     * containers until it is completed or cancelled: TripResourceAvailability, reading of ج3),
     * a fresh compatible pair, so a test can open several trips in a row.
     *
     * @return array{going_asset_id?: int, returning_asset_id: int}
     */
    protected function containers(bool $going): array
    {
        $held = fn (Asset $asset) => ! TripResourceAvailability::isAssetAvailable($asset);

        if ($held($this->returningAsset) || ($going && $held($this->goingAsset))) {
            $back = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
            $back->forceFill(['project_id' => $this->project->id])->save();
            $go = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
            $this->makeCompatible(null, $this->vehicle, null, [], [$back, $go]);

            return $going ? ['going_asset_id' => $go->id, 'returning_asset_id' => $back->id] : ['returning_asset_id' => $back->id];
        }

        return $going
            ? ['going_asset_id' => $this->goingAsset->id, 'returning_asset_id' => $this->returningAsset->id]
            : ['returning_asset_id' => $this->returningAsset->id];
    }

    /** The driver's create input. */
    protected function driverData(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_id' => $this->vehicle->id,
            'main_service_id' => $this->service->id,
            ...$this->containers(going: false),
            'location_url' => 'https://www.google.com/maps?q=24.7136,46.6753',
        ], $overrides);
    }

    protected function image(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40);
    }

    /** The signatures of a completing step: the client's uploaded by staff (د4) and the staff member's own (د5). */
    protected function completion(array $overrides = []): array
    {
        return array_merge([
            'client_signature' => $this->image('client-signature.png'),
            'client_signer_name' => 'Site Supervisor',
            'gcm_signature' => $this->image('gcm-signature.png'),
        ], $overrides);
    }

    protected function createAwaiting(array $overrides = [], ?User $by = null): Trip
    {
        return app(CreateTripByStaffAction::class)->execute($by ?? $this->admin, $this->staffData($overrides));
    }

    protected function createByDriver(array $overrides = []): Trip
    {
        return app(CreateTripByDriverAction::class)->execute($this->driver->user, $this->driverData($overrides), $this->image('live.png'));
    }

    protected function clientApprove(Trip $trip, array $input = [], ?User $client = null): Trip
    {
        return app(ClientApproveTripAction::class)->execute($trip, $client ?? $this->clientManager, ['signature' => $this->image('drawn.png'), ...$input]);
    }

    protected function clientReject(Trip $trip, string $reason = 'Wrong quantity'): Trip
    {
        return app(ClientRejectTripAction::class)->execute($trip, $this->clientManager, $reason);
    }

    protected function finalApprove(Trip $trip, array $completion = [], array $data = []): Trip
    {
        return app(FinalApproveTripAction::class)->execute($trip, $this->admin, ['gcm_signature' => $this->image('gcm.png'), ...$completion], $data);
    }

    protected function cancel(Trip $trip, ?string $reason = null): Trip
    {
        return app(CancelTripAction::class)->execute($trip, $this->admin, $reason);
    }

    /** A trip brought to `$status` through the real transitions. */
    protected function tripIn(string $status): Trip
    {
        $trip = $this->createAwaiting();

        return match ($status) {
            Trip::STATUS_AWAITING_CLIENT_APPROVAL => $trip,
            Trip::STATUS_CLIENT_APPROVED => $this->clientApprove($trip),
            Trip::STATUS_CLIENT_ISSUE => $this->clientReject($trip),
            Trip::STATUS_COMPLETED => $this->finalApprove($this->clientApprove($trip)),
            Trip::STATUS_CANCELLED => $this->cancel($trip),
        };
    }

    /** trips used on the line, from the ledger. */
    protected function tripsUsed(?PurchaseOrderService $line = null): int
    {
        return \App\Domain\PurchaseOrders\Quota\QuotaBalances::for($line ?? $this->line)->tripsUsed;
    }
}
