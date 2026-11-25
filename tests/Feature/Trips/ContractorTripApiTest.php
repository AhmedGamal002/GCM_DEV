<?php

namespace Tests\Feature\Trips;

use App\Models\Asset;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Testing\TestResponse;

/**
 * Part 7.7 — the contractor user's side of the trips API: the trips whose driver OR vehicle is his contractor's (not
 * the containers — ج19), what he sees of one (ج18: no costs, no PO number, no history; the facility and the delivery
 * note number yes), the delivery note document (8.1) but never the waste manifest or the combined PDF, his export,
 * and nothing he can change. One actor per test (the sanctum guard caches the first user).
 */
class ContractorTripApiTest extends TripTestCase
{
    private Contractor $contractor;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractor = Contractor::factory()->create(['name' => 'Desert Haulage']);
        $this->user = User::factory()->contractorUser($this->contractor)->create();
    }

    private function read(string $uri, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->user, 'web')->getJson('/api/v1/'.$uri);
    }

    /** The fixture's driver belongs to the contractor. */
    private function byContractorDriver(): void
    {
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $this->contractor->id]);
    }

    /** The fixture's vehicle belongs to the contractor. */
    private function byContractorVehicle(): void
    {
        Vehicle::whereKey($this->vehicle->id)->update(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);
    }

    // ── which trips ─────────────────────────────────────────────────

    public function test_he_sees_a_trip_of_his_driver(): void
    {
        $trip = $this->createAwaiting();
        $this->byContractorDriver();

        $this->assertSame([$trip->id], collect($this->read('trips')->assertOk()->json('data'))->pluck('id')->all());
    }

    public function test_he_sees_a_trip_of_his_vehicle(): void
    {
        $trip = $this->createAwaiting();
        $this->byContractorVehicle();

        $this->assertSame([$trip->id], collect($this->read('trips')->assertOk()->json('data'))->pluck('id')->all());
    }

    public function test_the_containers_do_not_count(): void
    {
        $trip = $this->createAwaiting();
        Asset::whereKey($this->goingAsset->id)->update(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);
        Asset::whereKey($this->returningAsset->id)->update(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);

        $this->assertSame([], $this->read('trips')->assertOk()->json('data'));
        $this->read("trips/{$trip->id}")->assertNotFound();
    }

    public function test_another_contractors_trip_is_a_404_and_not_in_the_list_or_the_counters(): void
    {
        $trip = $this->createAwaiting();
        $other = Contractor::factory()->create();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $other->id]);

        $this->assertSame([], $this->read('trips')->assertOk()->json('data'));
        $this->read('trips/stats')->assertOk()->assertJsonPath('data.total', 0);
        $this->read("trips/{$trip->id}")->assertNotFound();
        $this->read("trips/{$trip->id}/documents/delivery-note")->assertNotFound();
    }

    public function test_a_contractor_user_without_a_contractor_sees_nothing(): void
    {
        $this->createAwaiting();
        $this->byContractorDriver();
        $orphan = User::factory()->contractorUser($this->contractor)->create();
        $orphan->forceFill(['contractor_id' => null])->save();

        $this->assertSame([], $this->read('trips', $orphan)->assertOk()->json('data'));
        $this->read('trips/stats', $orphan)->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_the_counters_count_only_his_trips(): void
    {
        $this->createAwaiting();
        $this->byContractorDriver();
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $this->createAwaiting(['driver_id' => $other->id]);

        $this->read('trips/stats')->assertOk()->assertJsonPath('data.total', 1);
    }

    // ── what he sees of one ─────────────────────────────────────────

    public function test_he_sees_the_trip_as_it_is_but_no_costs_po_number_or_history(): void
    {
        $trip = $this->createAwaiting();
        $this->byContractorDriver();

        $response = $this->read("trips/{$trip->id}")->assertOk();

        $response->assertJsonPath('data.company.name', 'Al Nakheel')           // ج19: the client and the project as they are
            ->assertJsonPath('data.project.name', 'Riyadh Hotel')
            ->assertJsonPath('data.facility.id', $this->facility->id)           // ج18: the field side sees the facility
            ->assertJsonPath('data.linked_to_purchase_order', true)
            ->assertJsonMissingPath('data.trip_cost')
            ->assertJsonMissingPath('data.ancillary_cost')
            ->assertJsonMissingPath('data.purchase_order')
            ->assertJsonMissingPath('data.events')
            ->assertJsonMissingPath('data.actions')
            ->assertJsonMissingPath('data.driver_actions')
            ->assertJsonMissingPath('data.client_actions')
            ->assertJsonMissingPath('data.files');
    }

    public function test_he_opens_no_trip_file(): void
    {
        $trip = $this->createByDriver();
        $this->byContractorDriver();

        foreach (['live-photo', 'recycle-receipt', 'other', 'gcm-signature', 'client-signature'] as $kind) {
            $this->read("trips/{$trip->id}/files/{$kind}")->assertForbidden();
        }
    }

    // ── the documents (8.1): the delivery note only ─────────────────

    public function test_he_gets_the_delivery_note_of_a_completed_trip_but_neither_the_manifest_nor_the_combined_pdf(): void
    {
        $trip = $this->createAwaiting();
        $this->clientApprove($trip);
        $this->finalApprove($trip->fresh());
        $this->byContractorDriver();

        $documents = $this->read("trips/{$trip->id}")->assertOk()->json('data.documents');

        $this->assertArrayHasKey('delivery_note', $documents);
        $this->assertArrayNotHasKey('waste_manifest', $documents);
        $this->assertNull($documents['export_url']);

        $this->read("trips/{$trip->id}/documents/delivery-note")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->read("trips/{$trip->id}/documents/waste-manifest")->assertForbidden();
        $this->read("trips/{$trip->id}/documents/export")->assertForbidden();
    }

    public function test_before_completion_there_is_no_delivery_note_yet(): void
    {
        $trip = $this->createAwaiting();
        $this->byContractorDriver();

        $documents = $this->read("trips/{$trip->id}")->assertOk()->json('data.documents');

        $this->assertArrayNotHasKey('delivery_note', $documents);
        $this->read("trips/{$trip->id}/documents/delivery-note")->assertStatus(409);
    }

    // ── his export ──────────────────────────────────────────────────

    public function test_he_exports_his_list_with_the_columns_he_may_see(): void
    {
        $this->createAwaiting();
        $this->byContractorDriver();

        $this->read('trips/export?format=xlsx')->assertOk();

        // the export is the list he sees: another contractor's trips are not in it
        $headers = \App\Domain\Trips\Exports\TripsExport::headers($this->user);
        $this->assertNotContains('Trip cost', $headers);
        $this->assertContains('Driver affiliation', $headers, 'ج19: he sees the affiliations (a driver of another entity too).');
    }

    // ── nothing he can change ───────────────────────────────────────

    public function test_he_can_create_edit_cancel_or_approve_nothing(): void
    {
        $trip = $this->createAwaiting();
        $this->byContractorDriver();
        $this->actingAs($this->user, 'web');

        $this->postJson('/api/v1/trips', [])->assertForbidden();
        $this->postJson('/api/v1/driver/trips', [])->assertForbidden();
        $this->patchJson("/api/v1/trips/{$trip->id}", ['quantity_ton' => 1])->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/cancel")->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/final-approve")->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/client-approve")->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/remind-client")->assertForbidden();
        $this->postJson("/api/v1/driver/trips/{$trip->id}/recycle-receipt")->assertForbidden();
    }

    public function test_he_has_no_form_lists(): void
    {
        $this->actingAs($this->user, 'web');

        foreach (['drivers', 'vehicles', 'companies', 'projects', 'main-services', 'sub-services', 'going-assets', 'returning-assets', 'facilities'] as $list) {
            $this->getJson("/api/v1/trips/options/{$list}")->assertForbidden();
        }
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/trips')->assertUnauthorized();
    }
}
