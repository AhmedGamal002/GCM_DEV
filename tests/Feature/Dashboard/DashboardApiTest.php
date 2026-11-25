<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Notifications\Support\NotificationType;
use App\Domain\Notifications\Support\Notifier;
use App\Models\Asset;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Project;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.3 — the dashboard API: each role gets only the sections it may see, every number inside the scope of the
 * pages it summarises (a client his projects, a driver his own trips, a contractor user his drivers' and vehicles'
 * trips and his own resources), the figures are right, and the notification list is the caller's own latest ten.
 * One actor per test (the sanctum guard caches the first user).
 */
class DashboardApiTest extends TripTestCase
{
    private function dashboard(User $as): array
    {
        return $this->actingAs($as, 'web')->getJson('/api/v1/dashboard')->assertOk()->json('data');
    }

    private function statuses(array $data): array
    {
        return collect($data['trip_statuses'])->pluck('count', 'status')->all();
    }

    /** One completed, one awaiting, one cancelled trip — in the fixture's project. */
    private function threeTrips(): void
    {
        $this->tripIn(Trip::STATUS_COMPLETED);
        $this->tripIn(Trip::STATUS_AWAITING_CLIENT_APPROVAL);
        $this->tripIn(Trip::STATUS_CANCELLED);
    }

    // ── what each role gets ─────────────────────────────────────────

    public function test_staff_get_every_section(): void
    {
        $data = $this->dashboard($this->admin);

        foreach (['trips_30_days', 'trip_statuses', 'services_share', 'resources', 'purchase_orders', 'notifications', 'can_create_trip'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertCount(30, $data['trips_30_days']);
        $this->assertCount(5, $data['trip_statuses']);
    }

    public function test_the_auditor_gets_the_purchase_orders_section(): void
    {
        $this->assertArrayHasKey('purchase_orders', $this->dashboard($this->auditor));
    }

    public function test_data_entry_has_no_purchase_orders_section(): void
    {
        $data = $this->dashboard($this->dataEntry);

        $this->assertArrayNotHasKey('purchase_orders', $data);
        $this->assertArrayHasKey('resources', $data);
    }

    public function test_the_clients_project_manager_gets_trips_and_purchase_orders_but_no_resources(): void
    {
        $data = $this->dashboard($this->clientManager);

        $this->assertArrayHasKey('purchase_orders', $data);
        $this->assertArrayNotHasKey('resources', $data);
        $this->assertArrayHasKey('trip_statuses', $data);
    }

    public function test_the_clients_project_auditor_has_no_purchase_orders_either(): void
    {
        $data = $this->dashboard($this->clientAuditor);

        $this->assertArrayNotHasKey('purchase_orders', $data);
        $this->assertArrayNotHasKey('resources', $data);
    }

    public function test_the_driver_gets_his_trips_and_the_create_button_only(): void
    {
        $data = $this->dashboard($this->driver->user);

        $this->assertTrue($data['can_create_trip']);
        $this->assertArrayNotHasKey('resources', $data);
        $this->assertArrayNotHasKey('purchase_orders', $data);
    }

    public function test_only_the_driver_can_create_a_trip_from_the_dashboard(): void
    {
        $this->assertFalse($this->dashboard($this->admin)['can_create_trip']);
    }

    // ── the figures ─────────────────────────────────────────────────

    public function test_the_five_statuses_count_every_trip_and_the_chart_leaves_the_cancelled_out(): void
    {
        $this->threeTrips();

        $data = $this->dashboard($this->admin);

        $this->assertSame(['awaiting_client_approval' => 1, 'client_approved' => 0, 'client_issue' => 0, 'completed' => 1, 'cancelled' => 1], $this->statuses($data));
        $this->assertSame(2, array_sum(array_column($data['trips_30_days'], 'count')), 'the cancelled trip is not on the chart');
    }

    public function test_the_chart_covers_the_last_thirty_days_with_the_zero_days_and_today_last(): void
    {
        $this->createAwaiting(['trip_at' => '2026-02-20 09:00']); // "now" is 2026-03-01: 9 days ago
        $this->createAwaiting(['trip_at' => '2025-12-01 09:00']); // far out of the window

        $chart = $this->dashboard($this->admin)['trips_30_days'];

        $this->assertSame('2026-03-01', end($chart)['date']);
        $this->assertSame('2026-01-31', $chart[0]['date']);
        $this->assertSame(1, collect($chart)->firstWhere('date', '2026-02-20')['count']);
        $this->assertSame(1, array_sum(array_column($chart, 'count')));
    }

    public function test_the_services_share_sums_to_a_hundred_percent(): void
    {
        $this->threeTrips();

        $share = $this->dashboard($this->admin)['services_share'];

        $this->assertCount(1, $share);
        $this->assertSame('Solid Waste Transport', $share[0]['name']);
        $this->assertSame(2, $share[0]['count']);
        $this->assertEqualsWithDelta(100.0, $share[0]['percent'], 0.01);
    }

    public function test_the_resources_count_by_status(): void
    {
        Vehicle::whereKey($this->vehicle->id)->update(['operational_status' => 'on_maintenance']);
        Asset::whereKey($this->goingAsset->id)->update(['operational_status' => 'deactivated']);

        $resources = $this->dashboard($this->admin)['resources'];

        $this->assertSame(1, $resources['vehicles']['on_maintenance']);
        $this->assertSame(1, $resources['containers']['deactivated']);
        $this->assertSame(1, $resources['containers']['active']);
        $this->assertSame(0, array_sum($resources['tanks']));
        $this->assertSame(1, $resources['drivers']['active']);
    }

    public function test_purchase_orders_are_counted_by_their_items_status(): void
    {
        $this->line->forceFill(['max_trips' => 2, 'alert_percent' => 60])->save();
        $this->createAwaiting(); // one of two left: under the 60% line

        $this->assertSame(['valid' => 0, 'near_exhaustion' => 1, 'exhausted' => 0], $this->dashboard($this->admin)['purchase_orders']);
    }

    public function test_a_deactivated_purchase_order_is_not_counted(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();

        $this->assertSame(['valid' => 0, 'near_exhaustion' => 0, 'exhausted' => 0], $this->dashboard($this->admin)['purchase_orders']);
    }

    // ── scopes ──────────────────────────────────────────────────────

    public function test_staff_count_the_trips_of_every_project(): void
    {
        $this->createAwaiting();
        $other = Project::factory()->create();
        Trip::factory()->forProject($other)->create(['driver_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id, 'main_service_id' => $this->service->id]);

        $this->assertSame(2, array_sum($this->statuses($this->dashboard($this->admin))));
    }

    public function test_a_client_manager_sees_one_project_trip_not_the_other_companys(): void
    {
        $this->createAwaiting();
        $other = Project::factory()->create();
        Trip::factory()->forProject($other)->create(['driver_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id, 'main_service_id' => $this->service->id]);

        $this->assertSame(1, array_sum($this->statuses($this->dashboard($this->clientManager))));
    }

    public function test_a_driver_counts_only_his_own_trips(): void
    {
        $this->createByDriver();
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $this->createAwaiting(['driver_id' => $other->id]);

        $this->assertSame(1, array_sum($this->statuses($this->dashboard($this->driver->user))));
    }

    public function test_a_contractor_user_sees_his_trips_and_his_resources_only(): void
    {
        $contractor = Contractor::factory()->create();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $contractor->id]);
        Vehicle::whereKey($this->vehicle->id)->update(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        $this->createAwaiting();

        $data = $this->dashboard(User::factory()->contractorUser($contractor)->create());

        $this->assertSame(1, array_sum($this->statuses($data)));
        $this->assertSame(1, $data['resources']['vehicles']['active']);
        $this->assertSame(1, $data['resources']['drivers']['active']);
        $this->assertSame(0, array_sum($data['resources']['containers']), 'the containers are not his');
        $this->assertArrayNotHasKey('purchase_orders', $data);
    }

    public function test_a_contractor_user_of_another_contractor_sees_nothing(): void
    {
        $this->createAwaiting();

        $data = $this->dashboard(User::factory()->contractorUser(Contractor::factory()->create())->create());

        $this->assertSame(0, array_sum($this->statuses($data)));
        $this->assertSame(0, array_sum($data['resources']['vehicles']));
    }

    // ── notifications ───────────────────────────────────────────────

    public function test_the_notifications_are_the_callers_latest_ten(): void
    {
        foreach (range(1, 12) as $i) {
            Notifier::send($this->tenant->id, NotificationType::TRIP_CANCELLED, collect([$this->admin]), ['code' => "ALN-T{$i}"], subject: "ALN-T{$i}");
        }
        Notifier::send($this->tenant->id, NotificationType::TRIP_CANCELLED, collect([$this->dataEntry]), ['code' => 'ALN-X'], subject: 'ALN-X');

        $rows = $this->dashboard($this->admin)['notifications'];

        $this->assertCount(10, $rows);
        $this->assertSame('ALN-T12', $rows[0]['subject']);
        $this->assertNotContains('ALN-X', array_column($rows, 'subject'));
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}
