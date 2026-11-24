<?php

namespace Tests\Feature;

use App\Domain\Trips\Options\TripCompatibility;
use App\Domain\Trips\Options\TripOptions;
use App\Models\Driver;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TripDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The opt-in demo data for trying the trip forms (php artisan db:seed --class=TripDemoSeeder): every list of the form
 * has something to show and something to leave out, running it twice adds nothing, and it never runs in production.
 */
class TripDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(TripDemoSeeder::class);
    }

    public function test_every_list_has_something_to_show_and_something_to_leave_out(): void
    {
        $this->seedDemo();
        $project = Project::query()->orderBy('id')->firstOrFail();
        $driver = Driver::query()->firstOrFail();

        $this->assertCount(6, TripOptions::vehicles($driver)->get(), 'The driver is qualified for all six demo vehicles.');
        $this->assertSame('DMO 1002', $driver->defaultVehicle->plate(), 'His default vehicle takes external containers.');

        $embedded = Vehicle::query()->where('plate_numbers', '1001')->firstOrFail();
        $this->assertTrue($embedded->has_embedded_container);
        $this->assertFalse(TripCompatibility::takesExternalContainers($embedded));
        $this->assertSame([], TripOptions::goingAssets($embedded)->pluck('assets.name')->all(), 'A built-in body takes no external container.');
        $this->assertSame([], TripOptions::returningAssets($project, $embedded)->pluck('assets.name')->all());

        $hook = Vehicle::query()->where('plate_numbers', '1002')->firstOrFail();
        $this->assertEqualsCanonicalizing(['DMO-C1', 'DMO-C2', 'DMO-C3', 'DMO-T1', 'DMO-T2'], TripOptions::goingAssets($hook)->pluck('assets.name')->all());
        $this->assertContains('DMO-R1', TripOptions::returningAssets($project, $hook)->pluck('assets.name')->all(), 'The containers at the project are the returning ones.');
        $this->assertNotContains('DMO-R1', TripOptions::goingAssets($hook)->pluck('assets.name')->all(), 'An asset at a project is not in the pool.');

        $this->assertNotEmpty(TripOptions::mainServices($hook, $project)->get());
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        $this->seedDemo();
        [$vehicles, $orders] = [Vehicle::count(), PurchaseOrder::count()];

        $this->seed(TripDemoSeeder::class);

        $this->assertSame($vehicles, Vehicle::count());
        $this->assertSame($orders, PurchaseOrder::count());
        $this->assertSame(6, Vehicle::count());
        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);

        // not through `db:seed`: in production that command stops to ask for a confirmation first
        $this->app->make(TripDemoSeeder::class)->run();
    }
}
