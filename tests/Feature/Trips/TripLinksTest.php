<?php

namespace Tests\Feature\Trips;

use App\Domain\Services\Actions\UpdateMainServiceAction;
use App\Domain\Services\Actions\UpdateMainServiceStatusAction;
use App\Domain\Services\Exceptions\MainServiceInUseException;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\Trip;

/**
 * Part 7.7 — what the other modules know about trips: the "used" locks of the services (ج5: any trip that wasn't
 * cancelled, permanent) and the real trip figures on the details pages of a client company, a project, a main /
 * sub service and a vehicle. One actor per test.
 */
class TripLinksTest extends TripTestCase
{
    // ── the locks ───────────────────────────────────────────────────

    public function test_a_service_is_free_until_a_trip_uses_it(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save(); // no active PO either

        $this->assertFalse($this->sub->hasOngoingTrips());
        $this->assertFalse($this->service->isInUse());
        $this->assertFalse($this->sub->isLocked());
    }

    public function test_a_trip_that_is_not_cancelled_locks_both_services_for_good(): void
    {
        $trip = $this->createAwaiting();
        $this->po->forceFill(['operational_status' => 'deactivated'])->save(); // so only the trip locks them

        foreach ([Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_COMPLETED] as $status) {
            $trip->forceFill(['status' => $status])->save();

            $this->assertTrue(SubService::find($this->sub->id)->hasOngoingTrips(), "locked while {$status}");
            $this->assertTrue(MainService::find($this->service->id)->isInUse(), "locked while {$status}");
        }
    }

    public function test_a_cancelled_trip_does_not_lock_them(): void
    {
        $trip = $this->createAwaiting();
        $this->cancel($trip);
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();

        $this->assertFalse(SubService::find($this->sub->id)->hasOngoingTrips());
        $this->assertFalse(MainService::find($this->service->id)->isInUse());
    }

    public function test_a_main_service_used_by_a_trip_cannot_be_renamed_or_deactivated(): void
    {
        $this->createAwaiting();
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();
        $service = MainService::find($this->service->id);

        try {
            app(UpdateMainServiceAction::class)->execute($service, ['name' => 'Renamed'], $this->admin);
            $this->fail('Expected MainServiceInUseException');
        } catch (MainServiceInUseException) {
            $this->assertNotSame('Renamed', $service->fresh()->name);
        }

        $this->expectException(MainServiceInUseException::class);
        app(UpdateMainServiceStatusAction::class)->execute($service, 'deactivated', $this->admin);
    }

    // ── the figures ─────────────────────────────────────────────────

    public function test_the_company_and_the_project_count_their_trips_and_the_waste_moved(): void
    {
        $kept = $this->createAwaiting();
        $cancelled = $this->createAwaiting();
        $this->cancel($cancelled);
        $tons = (float) $kept->fresh()->quantity_ton;

        $this->actingAs($this->admin, 'web');
        foreach (["companies/{$this->company->id}", "projects/{$this->project->id}"] as $uri) {
            $stats = $this->getJson("/api/v1/{$uri}")->assertOk()->json('data.stats');

            $this->assertSame(1, $stats['trips'], "{$uri}: the cancelled trip is out");
            $this->assertEqualsWithDelta($tons, $stats['waste_tons'], 0.001);
        }
    }

    public function test_the_services_count_trips_projects_drivers_vehicles_and_containers(): void
    {
        $trip = $this->createAwaiting();
        $cancelled = $this->createAwaiting();
        $this->cancel($cancelled);

        $this->actingAs($this->admin, 'web');
        foreach (["main-services/{$this->service->id}", "sub-services/{$this->sub->id}"] as $uri) {
            $stats = $this->getJson("/api/v1/{$uri}")->assertOk()->json('data.stats');

            $this->assertSame(1, $stats['trips'], $uri);
            $this->assertSame(1, $stats['projects']);
            $this->assertSame(1, $stats['drivers']);
            $this->assertSame(1, $stats['vehicles']);
            $this->assertEqualsWithDelta((float) $trip->fresh()->quantity_ton, $stats['quantity_ton'], 0.001);
            $this->assertEqualsWithDelta((float) $trip->fresh()->quantity_cbm, $stats['quantity_cbm'], 0.001);
            // the going container (and the returning one, if any) — each counted once, by its type
            $this->assertSame($trip->going_asset_id ? 1 : 0, $stats['containers'] + $stats['tanks'] - ($trip->returning_asset_id ? 1 : 0), $uri);
        }
    }

    public function test_a_service_nobody_used_has_zero_figures(): void
    {
        $other = MainService::factory()->create();

        $stats = $this->actingAs($this->admin, 'web')->getJson("/api/v1/main-services/{$other->id}")->assertOk()->json('data.stats');

        $this->assertSame(0, $stats['trips']);
        $this->assertSame(0, $stats['containers']);
        $this->assertSame(0.0, (float) $stats['quantity_ton']);
    }

    public function test_the_vehicle_counts_its_trips_in_the_list_and_the_details(): void
    {
        $this->createAwaiting();
        $cancelled = $this->createAwaiting();
        $this->cancel($cancelled);

        $this->actingAs($this->admin, 'web');
        $row = collect($this->getJson('/api/v1/vehicles?per_page=50')->assertOk()->json('data'))->firstWhere('id', $this->vehicle->id);
        $this->assertSame(1, $row['trips_count']);

        $details = $this->getJson("/api/v1/vehicles/{$this->vehicle->id}")->assertOk()->json('data');
        $this->assertSame(1, $details['trips_count']);
        $this->assertSame(1, $details['trip_stats']['trips']);
    }

    public function test_the_purchase_order_shows_the_waste_moved_under_it(): void
    {
        $kept = $this->createAwaiting();
        $cancelled = $this->createAwaiting();
        $this->cancel($cancelled);

        $this->actingAs($this->admin, 'web');
        $totals = $this->getJson("/api/v1/purchase-orders/{$this->po->id}")->assertOk()->json('data.totals');

        $this->assertEqualsWithDelta((float) $kept->fresh()->quantity_ton, $totals['waste_tons'], 0.001);
    }
}
