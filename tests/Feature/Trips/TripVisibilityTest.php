<?php

namespace Tests\Feature\Trips;

use App\Http\Resources\TripResource;
use App\Models\Asset;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Project;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * Part 7.4 — who sees which trip (Trip::scopeVisibleTo: staff all, client its
 * projects, driver his own, contractor user ج19) and which field of it
 * (decision ج18, TripFieldVisibility → TripResource), for every role ×
 * every restricted field (security checklist 7.4). The resource is resolved
 * directly with each role: the client / driver / contractor endpoints open
 * in 7.5–7.7 on these same two rules.
 */
class TripVisibilityTest extends TripTestCase
{
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trip = $this->createAwaiting([
            'delivery_note_number' => 'DN-1', 'waste_manifest_number' => 'WM-1', 'recycle_receipt_number' => 'RR-1',
            'additional_data' => '<p>internal</p>',
        ]);
    }

    /** @return array<string, mixed> */
    private function seenBy(User $user): array
    {
        $this->actingAs($user, 'web');
        $request = request();
        $request->setUserResolver(fn () => $user);

        return TripResource::make($this->trip->fresh()->load(['events', 'purchaseOrderService.purchaseOrder', 'facility']))->resolve($request);
    }

    private function contractorUser(Contractor $contractor): User
    {
        return User::factory()->contractorUser($contractor)->create();
    }

    // ------------------------------------------------------------ ج18: role × field

    public function test_staff_see_every_field(): void
    {
        foreach ([$this->admin, $this->dataEntry, $this->auditor] as $staff) {
            $data = $this->seenBy($staff);

            $this->assertEquals(100, $data['trip_cost']);
            $this->assertArrayHasKey('ancillary_cost', $data);
            $this->assertSame('ALN-1200', $data['purchase_order']['number']);
            $this->assertSame($this->facility->id, $data['facility']['id']);
            $this->assertSame(['DN-1', 'WM-1', 'RR-1'], [$data['delivery_note_number'], $data['waste_manifest_number'], $data['recycle_receipt_number']]);
            $this->assertArrayHasKey('events', $data);
            $this->assertArrayHasKey('files', $data);
            $this->assertArrayHasKey('additional_data', $data);
        }
    }

    public function test_the_client_manager_sees_the_po_number_but_no_cost_facility_or_document_number(): void
    {
        $data = $this->seenBy($this->clientManager);

        $this->assertSame('ALN-1200', $data['purchase_order']['number']);
        $this->assertTrue($data['linked_to_purchase_order']);
        $this->assertNoneOf($data, ['trip_cost', 'ancillary_cost', 'facility', 'delivery_note_number', 'waste_manifest_number', 'recycle_receipt_number', 'events', 'additional_data', 'actions']);
        $this->assertArrayHasKey('client_comment', $data);
        // 7.5: the live photo and the client's own attachment only — no signatures, stamps or staff documents.
        $this->assertSame(['live-photo', 'client-attachment'], array_keys($data['files']));
    }

    public function test_the_client_auditor_sees_neither_po_number_cost_facility_nor_document_number(): void
    {
        $data = $this->seenBy($this->clientAuditor);

        $this->assertTrue($data['linked_to_purchase_order'], 'Linked or not — not which PO.');
        $this->assertNoneOf($data, ['purchase_order', 'trip_cost', 'ancillary_cost', 'facility', 'delivery_note_number', 'waste_manifest_number', 'recycle_receipt_number', 'events']);
        $this->assertSame(['live-photo', 'client-attachment'], array_keys($data['files']));
    }

    public function test_the_driver_sees_the_facility_and_the_delivery_note_number_only(): void
    {
        $data = $this->seenBy($this->driver->user);

        $this->assertSame($this->facility->id, $data['facility']['id']);
        $this->assertSame('DN-1', $data['delivery_note_number']);
        $this->assertNoneOf($data, ['purchase_order', 'trip_cost', 'ancillary_cost', 'waste_manifest_number', 'recycle_receipt_number', 'client_comment', 'events', 'files', 'additional_data', 'actions']);
    }

    public function test_the_contractor_user_sees_what_the_driver_sees(): void
    {
        $data = $this->seenBy($this->contractorUser(Contractor::factory()->create()));

        $this->assertSame($this->facility->id, $data['facility']['id']);
        $this->assertSame('DN-1', $data['delivery_note_number']);
        $this->assertNoneOf($data, ['purchase_order', 'trip_cost', 'ancillary_cost', 'waste_manifest_number', 'recycle_receipt_number', 'events', 'files']);
    }

    // ------------------------------------------------------------ the visibility scope

    public function test_staff_see_every_trip(): void
    {
        $this->assertTrue($this->trip->isVisibleTo($this->auditor));
        $this->assertTrue($this->trip->isVisibleTo($this->dataEntry));
    }

    public function test_a_client_sees_the_trips_of_the_projects_it_sees(): void
    {
        $this->assertTrue($this->trip->isVisibleTo($this->clientManager), 'All projects of the company.');

        $other = Project::factory()->for($this->company)->create();
        $limited = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        $limited->projects()->sync([$other->id]);

        $this->assertFalse($this->trip->isVisibleTo($limited), 'Only its own projects.');
        $this->assertFalse($this->trip->isVisibleTo(User::factory()->client(\App\Models\Company::factory()->create(), 'client_project_manager')->create()), 'Another company.');
    }

    public function test_a_driver_sees_his_own_trips_only(): void
    {
        $this->assertTrue($this->trip->isVisibleTo($this->driver->user));
        $this->assertFalse($this->trip->isVisibleTo(Driver::factory()->create()->user));
    }

    public function test_a_contractor_user_sees_trips_of_its_driver_or_vehicle_not_of_its_containers(): void
    {
        $contractor = Contractor::factory()->create();
        $user = $this->contractorUser($contractor);

        $this->assertFalse($this->trip->isVisibleTo($user));

        Asset::whereKey($this->goingAsset->id)->update(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        $this->assertFalse($this->trip->isVisibleTo($user), 'ج19: containers don\'t count.');

        Vehicle::whereKey($this->vehicle->id)->update(['affiliation' => 'contractor', 'contractor_id' => $contractor->id]);
        $this->assertTrue($this->trip->isVisibleTo($user), 'Its vehicle.');

        Vehicle::whereKey($this->vehicle->id)->update(['affiliation' => 'gcm', 'contractor_id' => null]);
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $contractor->id]);
        $this->assertTrue($this->trip->isVisibleTo($user), 'Its driver.');
    }

    public function test_a_contractor_user_without_contractor_sees_nothing(): void
    {
        $user = User::factory()->contractorUser(Contractor::factory()->create())->create();
        $user->forceFill(['contractor_id' => null])->save();

        $this->assertSame(0, Trip::query()->visibleTo($user)->count());
    }

    private function assertNoneOf(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $data, "The field `{$key}` must not be sent.");
        }
    }
}
