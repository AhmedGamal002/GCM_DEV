<?php

namespace Tests\Feature\Trips;

use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Part 7.6 — the driver's side of the trips API: create with the live location and photo (ج12), his own list and
 * details, the recycle receipt photo until the trip completes (ج13), and nothing else (no edit, no other
 * driver's trips). One actor per test (the sanctum guard caches the first user).
 */
class DriverTripApiTest extends TripTestCase
{
    private const LOCATION = 'https://www.google.com/maps/search/?api=1&query=24.713600,46.675300';

    private function send(string $uri, array $data = [], $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->driver->user, 'web')->post('/api/v1/'.$uri, $data, ['Accept' => 'application/json']);
    }

    private function form(array $overrides = []): array
    {
        return [
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_id' => $this->vehicle->id,
            'main_service_id' => $this->service->id,
            ...$this->containers(going: false),
            'location_url' => self::LOCATION,
            'live_photo' => $this->image('live.png'),
            ...$overrides,
        ];
    }

    // ── create ──────────────────────────────────────────────────────

    public function test_the_driver_creates_a_trip_with_the_minimum_of_data(): void
    {
        $response = $this->send('driver/trips', $this->form())->assertCreated();

        $response->assertJsonPath('data.status', Trip::STATUS_AWAITING_CLIENT_APPROVAL)
            ->assertJsonPath('data.source', Trip::SOURCE_DRIVER)
            ->assertJsonPath('data.driver.id', $this->driver->id)
            ->assertJsonPath('data.driver_actions.attach_recycle_receipt', true)
            ->assertJsonPath('data.linked_to_purchase_order', true)
            ->assertJsonMissingPath('data.trip_cost')
            ->assertJsonMissingPath('data.purchase_order')
            ->assertJsonMissingPath('data.actions');

        $trip = Trip::findOrFail($response->json('data.id'));
        $this->assertSame($this->driver->id, $trip->driver_id);
        $this->assertNotNull($trip->live_photo_path);
        $this->assertSame(self::LOCATION, $trip->location_url);
    }

    public function test_a_driver_cannot_create_without_the_live_photo(): void
    {
        $this->send('driver/trips', $this->form(['live_photo' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('live_photo');
    }

    public function test_a_driver_cannot_create_without_the_location(): void
    {
        $this->send('driver/trips', $this->form(['location_url' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('location_url');
    }

    public function test_a_hand_typed_location_link_is_refused(): void
    {
        foreach (['https://example.com/here', 'javascript:alert(1)', 'https://www.google.com/maps?q=24.7,46.6', 'my site'] as $typed) {
            $this->send('driver/trips', $this->form(['location_url' => $typed]))
                ->assertUnprocessable()->assertJsonValidationErrors('location_url');
        }
    }

    public function test_the_photo_must_be_a_real_image(): void
    {
        $html = tempnam(sys_get_temp_dir(), 'trip');
        file_put_contents($html, '<html><script>alert(1)</script></html>');

        $this->send('driver/trips', $this->form(['live_photo' => new UploadedFile($html, 'photo.png', 'image/png', null, true)]))
            ->assertUnprocessable()->assertJsonValidationErrors('live_photo');
    }

    public function test_what_the_driver_must_not_choose_is_ignored(): void
    {
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);

        $response = $this->send('driver/trips', $this->form([
            'driver_id' => $other->id,
            'trip_cost' => '99999',
            'status' => Trip::STATUS_COMPLETED,
            'requires_client_approval' => '0',
            'trip_at' => '2020-01-01 00:00:00',
        ]))->assertCreated();

        $trip = Trip::findOrFail($response->json('data.id'));
        $this->assertSame($this->driver->id, $trip->driver_id);
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->status);
        $this->assertEquals(100, $trip->trip_cost, 'The cost is the PO line\'s, not the driver\'s.');
        $this->assertTrue($trip->trip_at->isSameDay(now()), 'The date is the moment of creation.');
    }

    public function test_the_choices_are_rechecked_for_the_driver_too(): void
    {
        // a vehicle his categories don't cover
        $other = \App\Models\Vehicle::factory()->create();

        $this->send('driver/trips', $this->form(['vehicle_id' => $other->id]))->assertUnprocessable();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonDrivers')]
    public function test_only_a_driver_may_use_the_drivers_create(string $property): void
    {
        $this->send('driver/trips', $this->form(), $this->{$property})->assertForbidden();
    }

    public static function nonDrivers(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client manager' => ['clientManager']];
    }

    public function test_a_guest_cannot_create(): void
    {
        $this->postJson('/api/v1/driver/trips', [])->assertUnauthorized();
    }

    // ── his own list and details ────────────────────────────────────

    public function test_the_driver_sees_his_trips_and_never_another_drivers(): void
    {
        $mine = $this->createByDriver();

        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $theirs = $this->createAwaiting(['driver_id' => $other->id, ...$this->containers(going: false)]);

        $list = $this->actingAs($this->driver->user, 'web')->getJson('/api/v1/trips')->assertOk();
        $this->assertSame([$mine->id], collect($list->json('data'))->pluck('id')->all());
        $this->assertNotContains($theirs->id, collect($list->json('data'))->pluck('id')->all());
    }

    public function test_another_drivers_trip_is_a_404(): void
    {
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $theirs = $this->createAwaiting(['driver_id' => $other->id]);

        $this->actingAs($this->driver->user, 'web')->getJson("/api/v1/trips/{$theirs->id}")->assertNotFound();
    }

    public function test_the_driver_never_sees_costs_the_po_number_or_the_history(): void
    {
        $trip = $this->createByDriver();

        $this->actingAs($this->driver->user, 'web')->getJson("/api/v1/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.trip_cost')
            ->assertJsonMissingPath('data.ancillary_cost')
            ->assertJsonMissingPath('data.purchase_order')
            ->assertJsonMissingPath('data.events')
            ->assertJsonMissingPath('data.gcm_approver');
    }

    public function test_the_driver_opens_his_photo_and_nothing_of_the_staffs(): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->driver->user, 'web');

        $this->get("/api/v1/trips/{$trip->id}/files/live-photo")->assertOk();
        $this->get("/api/v1/trips/{$trip->id}/files/gcm-signature")->assertForbidden();
        $this->get("/api/v1/trips/{$trip->id}/files/client-signature")->assertForbidden();
    }

    // ── no edit ─────────────────────────────────────────────────────

    public function test_a_driver_cannot_edit_cancel_or_approve_his_trip(): void
    {
        $trip = $this->createByDriver();
        $this->actingAs($this->driver->user, 'web');

        $this->patchJson("/api/v1/trips/{$trip->id}", ['quantity_ton' => 1])->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/cancel")->assertForbidden();
        $this->postJson("/api/v1/trips/{$trip->id}/final-approve")->assertForbidden();
        $this->getJson('/api/v1/trips/export')->assertForbidden();
    }

    // ── the recycle receipt photo ───────────────────────────────────

    public function test_the_driver_adds_and_replaces_the_receipt_photo(): void
    {
        $trip = $this->createByDriver();

        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => $this->image('receipt.png')])
            ->assertOk()->assertJsonPath('data.driver_actions.attach_recycle_receipt', true);

        $first = $trip->fresh()->recycle_receipt_path;
        $this->assertNotNull($first);
    }

    public function test_the_receipt_can_be_replaced_until_the_trip_completes(): void
    {
        $trip = $this->createByDriver();
        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => $this->image('one.png')])->assertOk();
        $first = $trip->fresh()->recycle_receipt_path;

        // a second request is a fresh test actor: do it in the same one, the driver
        $this->actingAs($this->driver->user, 'web')->post("/api/v1/driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => $this->image('two.png')], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotSame($first, $trip->fresh()->recycle_receipt_path);
    }

    public function test_after_completion_the_driver_can_no_longer_change_the_receipt(): void
    {
        $trip = $this->createByDriver();
        $this->clientApprove($trip);
        // the driver left the sub-service and the facility to GCM (the FRD's minimum of data)
        $this->finalApprove($trip->fresh(), [], ['sub_service_id' => $this->sub->id, 'intermediate_facility_id' => $this->facility->id]);
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->fresh()->status);

        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => $this->image('late.png')])
            ->assertStatus(409)->assertJsonPath('code', 'trip_transition_not_allowed');
    }

    public function test_a_driver_cannot_attach_to_another_drivers_trip(): void
    {
        $other = Driver::factory()->create();
        $this->makeCompatible($other, $this->vehicle);
        $theirs = $this->createAwaiting(['driver_id' => $other->id]);

        $this->send("driver/trips/{$theirs->id}/recycle-receipt", ['recycle_receipt' => $this->image('x.png')])->assertNotFound();
    }

    public function test_the_receipt_must_be_a_real_file_of_an_allowed_type(): void
    {
        $trip = $this->createByDriver();
        $html = tempnam(sys_get_temp_dir(), 'rcp');
        file_put_contents($html, '<html></html>');

        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => new UploadedFile($html, 'receipt.png', 'image/png', null, true)])
            ->assertUnprocessable()->assertJsonValidationErrors('recycle_receipt');
    }

    public function test_staff_cannot_use_the_drivers_receipt_endpoint(): void
    {
        $trip = $this->createByDriver();

        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => $this->image('x.png')], $this->admin)->assertForbidden();
    }
    public function test_the_receipt_is_a_photo_a_pdf_is_refused(): void
    {
        $trip = $this->createByDriver();

        // Part 8.1: the receipt is printed as a page of the trip's combined PDF — and the FRD says "photo".
        $this->send("driver/trips/{$trip->id}/recycle-receipt", ['recycle_receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('recycle_receipt');
    }

    public function test_the_driver_sees_his_delivery_note_once_the_trip_is_completed_and_never_the_manifest_or_the_export(): void
    {
        $trip = $this->createByDriver();
        $this->clientApprove($trip);
        $this->finalApprove($trip->fresh(), [], ['sub_service_id' => $this->sub->id, 'intermediate_facility_id' => $this->facility->id]);

        $documents = $this->actingAs($this->driver->user, 'web')->getJson("/api/v1/trips/{$trip->id}")
            ->assertOk()->json('data.documents');

        $this->assertArrayHasKey('delivery_note', $documents);
        $this->assertArrayNotHasKey('waste_manifest', $documents);
        $this->assertNull($documents['export_url']);
    }
}
