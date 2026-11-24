<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

/**
 * A ready-to-try set for the TRIP forms (parts 7.4 / 7.6) — NOT part of DatabaseSeeder, run it on purpose:
 *
 *     php artisan db:seed --class=TripDemoSeeder
 *
 * It gives the demo driver and the demo projects what a trip needs to be created, with a spread of cases so
 * every list of the form has something to show (and something it must leave out):
 *
 *  - vehicles: a truck with a BUILT-IN container, two with a built-in TANK (a water tank and a vacuum tanker),
 *    and three with no body of their own that take EXTERNAL containers / tanks (the "going" and "returning" lists);
 *  - assets: containers and tanks in the pool (going), and containers / a tank already placed at the first project
 *    (returning), each suited only to the categories of the external-body vehicles — so the going list of a
 *    vehicle with a built-in body is empty, as the FRD says;
 *  - the driver is qualified for every vehicle category and his default vehicle is one of them;
 *  - every main service supports all categories, every sub-service takes all capacities and has a facility;
 *  - one running PO per demo project, with a line for each main service that is not "supplying".
 *
 * Safe to run twice (it looks things up first), refused in production, and it needs the demo data of
 * DatabaseSeeder (driver, companies, projects, services, facilities) to exist already.
 */
class TripDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('TripDemoSeeder is demo data and must not run in production.');
        }

        if (! app()->bound('tenant')) {
            app()->instance('tenant', Tenant::query()->firstOrFail());
        }

        $categories = VehicleCategory::query()->orderBy('id')->get();
        $capacity = fn (string $name) => AssetCapacityCategory::query()->where('name', $name)->firstOrFail();

        [$bodyless, $embeddedCategory] = [$categories->slice(1)->values(), $categories->first()];

        // ── vehicles ────────────────────────────────────────────────
        $vehicles = [
            ['DMO', '1001', $embeddedCategory, 'container', 'Standard skip'],   // a built-in container
            ['DMO', '1002', $bodyless[0], null, null],                            // takes external containers / tanks
            ['DMO', '1003', $bodyless[1], 'tank', 'Water tank'],                // a built-in tank
            ['DMO', '1004', $bodyless[2], 'tank', 'Vacuum tanker'],             // a built-in tank ('both')
            ['DMO', '1005', $bodyless[0], null, null],
            ['DMO', '1006', $bodyless[3], null, null],
        ];
        $made = [];

        foreach ($vehicles as [$letters, $numbers, $category, $type, $capacityName]) {
            $made[] = Vehicle::query()->firstOrCreate(
                ['plate_letters' => $letters, 'plate_numbers' => $numbers],
                [
                    'vehicle_category_id' => $category->id,
                    'has_embedded_container' => $type !== null,
                    'embedded_container_type' => $type,
                    'embedded_asset_capacity_category_id' => $type === null ? null : $capacity($capacityName)->id,
                    'affiliation' => 'gcm',
                ],
            );
        }

        // ── assets: the external-body vehicles' categories take them ──
        $external = [$bodyless[0], $bodyless[3]];
        $externalIds = collect($external)->pluck('id')->all();
        $project = Project::query()->orderBy('id')->firstOrFail();

        $assets = [
            ['DMO-C1', 'container', 'Small skip', false],
            ['DMO-C2', 'container', 'Standard skip', false],
            ['DMO-C3', 'container', 'Roll-on/off', false],
            ['DMO-T1', 'tank', 'Water tank', false],
            ['DMO-T2', 'tank', 'Vacuum tanker', false],
            ['DMO-R1', 'container', 'Standard skip', true],   // at the project: returning containers
            ['DMO-R2', 'container', 'Roll-on/off', true],
            ['DMO-R3', 'tank', 'Water tank', true],
        ];

        foreach ($assets as [$name, $type, $capacityName, $atProject]) {
            $asset = Asset::query()->firstOrCreate(
                ['name' => $name],
                ['asset_type' => $type, 'asset_capacity_category_id' => $capacity($capacityName)->id, 'affiliation' => 'gcm'],
            );

            if ($atProject && $asset->project_id === null) {
                $asset->forceFill(['project_id' => $project->id])->save(); // placed by the trip flow in real life
            }

            $asset->compatibleVehicleCategories()->syncWithoutDetaching($externalIds);
        }

        // ── the driver ──────────────────────────────────────────────
        $driver = Driver::query()->orderBy('id')->firstOrFail();
        $driver->qualifiedVehicleCategories()->syncWithoutDetaching($categories->pluck('id')->all());
        $driver->forceFill(['default_vehicle_id' => $made[1]->id])->save();

        // ── services: everything is compatible, every sub-service has a facility ──
        $capacityIds = AssetCapacityCategory::query()->pluck('id')->all();
        $facility = IntermediateFacility::query()->orderBy('id')->firstOrFail();

        foreach (MainService::query()->get() as $service) {
            $service->vehicleCategories()->syncWithoutDetaching($categories->pluck('id')->all());
        }

        foreach (SubService::query()->get() as $sub) {
            $sub->capacities()->syncWithoutDetaching($capacityIds);

            if (! $sub->facilities()->exists()) {
                $sub->facilities()->attach($facility->id);
            }
        }

        // ── one running PO per demo project, a line per main service that carries waste ──
        foreach (Project::query()->orderBy('id')->take(2)->get() as $i => $demoProject) {
            $number = $demoProject->company->prefix.'-'.(9001 + $i);

            if (PurchaseOrder::query()->where('number', $number)->exists()) {
                continue;
            }

            $po = PurchaseOrder::factory()->forProject($demoProject)
                ->between(now()->subMonth()->toDateString(), now()->addMonths(6)->toDateString())
                ->create(['number' => $number]);

            foreach (MainService::query()->where('service_type', '!=', 'supplying')->get() as $service) {
                PurchaseOrderService::factory()->create([
                    'purchase_order_id' => $po->id,
                    'main_service_id' => $service->id,
                    'max_trips' => 90,
                    'trip_cost' => 100,
                ]);
            }
        }

        $this->command?->info('Trip demo data ready: '.count($made).' vehicles, '.count($assets).' assets, a PO per demo project; driver@gcm.test is qualified for every category.');
    }
}
