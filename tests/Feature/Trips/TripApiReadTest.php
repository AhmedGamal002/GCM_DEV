<?php

namespace Tests\Feature\Trips;

use App\Models\Contractor;
use App\Models\Driver;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Part 7.4 — GCM's trips list, counters, details, files and export
 * (/api/v1/trips…): who may read (System Admin, Data Entry, Auditor — the
 * other roles open in 7.5–7.7), the FRD filters and search, newest first,
 * the details with the history (ج8), and no N+1 on the list.
 *
 * One actor per test method (the actingAs cache trap, CLAUDE.md).
 */
class TripApiReadTest extends TripTestCase
{
    private function as(User $user, string $uri): TestResponse
    {
        return $this->actingAs($user, 'web')->getJson('/api/v1/'.$uri);
    }

    /** @return list<int> */
    private function listIds(string $query = ''): array
    {
        return collect($this->as($this->admin, 'trips'.($query === '' ? '' : '?'.$query))->assertOk()->json('data'))->pluck('id')->all();
    }

    // ------------------------------------------------------------ who may read

    public function test_staff_and_the_auditor_read_the_list(): void
    {
        $this->createAwaiting();

        $this->as($this->dataEntry, 'trips')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_auditor_reads_the_list_and_the_counters(): void
    {
        $this->createAwaiting();

        $this->as($this->auditor, 'trips')->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->auditor, 'trips/stats')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_a_driver_gets_403_until_7_6(): void
    {
        $this->as($this->driver->user, 'trips')->assertForbidden();
    }

    public function test_a_client_gets_403_until_7_5(): void
    {
        $this->as($this->clientManager, 'trips')->assertForbidden();
    }

    public function test_a_contractor_user_gets_403_until_7_7(): void
    {
        $this->as(User::factory()->contractorUser(Contractor::factory()->create())->create(), 'trips')->assertForbidden();
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/trips')->assertUnauthorized();
    }

    public function test_another_tenants_trip_is_a_404(): void
    {
        $trip = $this->createAwaiting();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $stranger = User::factory()->create(['tenant_id' => $other->id]);
        $stranger->assignRole('system_admin');

        $this->as($stranger, "trips/{$trip->id}")->assertNotFound();
    }

    // ------------------------------------------------------------ list: order, filters, search

    public function test_the_list_is_newest_first_and_sortable(): void
    {
        $old = $this->createAwaiting(['trip_at' => '2026-02-01 08:00']);
        $new = $this->createAwaiting(['trip_at' => '2026-02-20 08:00']);

        $this->assertSame([$new->id, $old->id], $this->listIds());
        $this->assertSame([$old->id, $new->id], $this->listIds('sort_by=code&sort_dir=asc'));
        $this->assertSame([$new->id, $old->id], $this->listIds('sort_by=DROP TABLE&sort_dir=desc'), 'Unknown column: the default.');
    }

    public function test_filters_by_date_status_and_po_link(): void
    {
        $feb = $this->createAwaiting(['trip_at' => '2026-02-10 08:00']);
        $mar = $this->createAwaiting(['trip_at' => '2026-03-01 08:00', 'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 50]);
        $this->cancel($mar);

        $this->assertSame([$feb->id], $this->listIds('date_from=2026-02-01&date_to=2026-02-28'));
        $this->assertSame([$mar->id], $this->listIds('date_from=2026-03-01&date_to=2026-03-01'), 'The end day is included.');
        $this->assertSame([$mar->id], $this->listIds('status=cancelled'));
        $this->assertSame([$feb->id], $this->listIds('purchase_order=linked'));
        $this->assertSame([$mar->id], $this->listIds('purchase_order=none'));
    }

    public function test_filters_by_driver_vehicle_client_project_and_service(): void
    {
        $mine = $this->createAwaiting();
        $otherProject = Project::factory()->for($this->company)->create();
        $back = $this->freshReturning($otherProject);
        $there = $this->createAwaiting(['project_id' => $otherProject->id, 'returning_asset_id' => $back->id, 'going_asset_id' => null, 'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 20, 'sub_service_id' => $this->receiptSub->id]);

        $this->assertSame([$mine->id], $this->listIds("project_id={$this->project->id}"));
        $this->assertEqualsCanonicalizing([$mine->id, $there->id], $this->listIds("company_id={$this->company->id}&driver_id={$this->driver->id}&vehicle_id={$this->vehicle->id}&main_service_id={$this->service->id}"));
        $this->assertSame([$there->id], $this->listIds("sub_service_id={$this->receiptSub->id}"));
    }

    public function test_filters_by_affiliation_and_contractor(): void
    {
        $contractor = Contractor::factory()->create();
        $theirs = Driver::factory()->forContractor($contractor)->create();
        $this->makeCompatible($theirs, $this->vehicle);
        $gcm = $this->createAwaiting();
        $byContractorDriver = $this->createAwaiting(['driver_id' => $theirs->id]);

        $this->assertSame([$gcm->id], $this->listIds('driver_affiliation=gcm'));
        $this->assertSame([$byContractorDriver->id], $this->listIds('driver_affiliation=contractor'));
        $this->assertSame([$byContractorDriver->id], $this->listIds("contractor_id={$contractor->id}"), 'ج19: the driver OR the vehicle of the contractor.');
        $this->assertEqualsCanonicalizing([$gcm->id, $byContractorDriver->id], $this->listIds('vehicle_affiliation=gcm'));
    }

    public function test_the_search_covers_every_text_column_of_the_table(): void
    {
        $trip = $this->createAwaiting();

        foreach ([$trip->code, 'Driver One', 'ABC 1234', 'ABC1234', 'Al Nakheel', 'Riyadh Hotel', 'General collection', 'ALN-1200'] as $term) {
            $this->assertSame([$trip->id], $this->listIds('search='.urlencode($term)), "Search: {$term}");
        }

        $this->assertSame([], $this->listIds('search=nothing-like-this'));
    }

    public function test_the_list_does_not_query_per_row(): void
    {
        $this->createAwaiting();
        $this->actingAs($this->admin, 'web');

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/trips')->assertOk();

            return count(DB::getQueryLog());
        };

        $one = $count();
        foreach (range(1, 4) as $i) {
            $this->createAwaiting(['trip_at' => "2026-02-0{$i} 08:00"]);
        }

        $this->assertLessThanOrEqual($one, $count(), 'Five rows cost no more queries than one row.');
    }

    // ------------------------------------------------------------ counters

    public function test_the_counters_give_every_status(): void
    {
        $this->createAwaiting();
        $this->cancel($this->createAwaiting());
        $this->tripIn(Trip::STATUS_COMPLETED);

        $stats = $this->as($this->admin, 'trips/stats')->assertOk()->json('data');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(
            ['awaiting_client_approval' => 1, 'client_approved' => 0, 'client_issue' => 0, 'completed' => 1, 'cancelled' => 1],
            collect($stats['by_status'])->pluck('count', 'status')->all(),
        );
        $this->assertNotEmpty($stats['by_status'][0]['label']);
    }

    // ------------------------------------------------------------ details

    public function test_the_details_carry_everything_for_staff_with_the_history(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $data = $this->as($this->admin, "trips/{$trip->id}")->assertOk()->json('data');

        $this->assertSame($trip->code, $data['code']);
        $this->assertSame('ALN-1200', $data['purchase_order']['number']);
        $this->assertEquals(100, $data['trip_cost']);
        $this->assertSame($this->facility->id, $data['facility']['id']);
        $this->assertSame($this->clientManager->id, $data['project']['representative']['id'], 'ج18: the field officer = the project representative.');
        $this->assertSame(['created', 'client_approved', 'final_approved'], array_values(array_intersect(array_column($data['events'], 'type'), ['created', 'client_approved', 'final_approved'])));
        $this->assertNotSame('', $data['events'][0]['type_label']);
        $this->assertTrue($data['actions']['cancel']);
        $this->assertFalse($data['actions']['final_approve']);
        $this->assertNotNull($data['files']['gcm-signature']);
    }

    public function test_the_auditor_sees_the_details_and_history_but_no_action(): void
    {
        $trip = $this->createAwaiting();

        $data = $this->as($this->auditor, "trips/{$trip->id}")->assertOk()->json('data');

        $this->assertNotEmpty($data['events']);
        $this->assertSame([false], array_values(array_unique($data['actions'])));
    }

    // ------------------------------------------------------------ files

    public function test_a_trip_file_opens_inline_for_staff(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $this->actingAs($this->auditor, 'web')->get("/api/v1/trips/{$trip->id}/files/gcm-signature")->assertOk();
        $this->actingAs($this->auditor, 'web')->get("/api/v1/trips/{$trip->id}/files/live-photo")->assertNotFound();
        $this->actingAs($this->auditor, 'web')->get("/api/v1/trips/{$trip->id}/files/passwd")->assertNotFound();
    }

    // ------------------------------------------------------------ export

    public function test_the_export_follows_the_filters_in_both_formats(): void
    {
        $this->createAwaiting();
        $this->actingAs($this->auditor, 'web');

        $this->get('/api/v1/trips/export?format=xlsx')->assertOk();
        $this->get('/api/v1/trips/export?format=pdf&status=completed')->assertOk();
    }

    public function test_a_driver_cannot_export(): void
    {
        $this->actingAs($this->driver->user, 'web')->get('/api/v1/trips/export')->assertForbidden();
    }

    private function freshReturning(Project $project): \App\Models\Asset
    {
        $asset = \App\Models\Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $asset->forceFill(['project_id' => $project->id])->save();
        $this->makeCompatible(null, $this->vehicle, null, [$this->sub, $this->receiptSub], [$asset]);

        return $asset;
    }
}
