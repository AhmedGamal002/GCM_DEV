<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Exports\TripsExport;
use App\Models\OtpChallenge;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Part 7.5 — the client's trips through the API (FRD V01.14 §1.14 "مراجعة
 * العميل للرحلات"): the client's project manager / auditor see the trips of
 * the projects they see (another project's trip is a 404 everywhere), with
 * the fields of decision ج18; they approve — sub-service only, signature
 * (drawn or saved, "save my signature"), optional stamp, attachment, comment
 * — or reject with the reasons, both behind the e-mailed code.
 *
 * One actor per test method (the actingAs cache trap, CLAUDE.md).
 */
class ClientTripApiTest extends TripTestCase
{
    private function verified(User $user): void
    {
        OtpChallenge::create([
            'user_id' => $user->id,
            'scope' => 'user:'.$user->id,
            'code_hash' => str_repeat('a', 64),
            'attempts' => 0,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);
    }

    private function read(User $user, string $uri): TestResponse
    {
        return $this->actingAs($user, 'web')->getJson('/api/v1/'.$uri);
    }

    private function send(User $user, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user, 'web')->post('/api/v1/'.$uri, $data, ['Accept' => 'application/json']);
    }

    /** A trip on a second project of the same company — one the "specific projects" client below doesn't see. */
    private function otherProjectTrip(): Trip
    {
        $other = Project::factory()->for($this->company)->create(['name' => 'Jeddah Tower']);

        return Trip::factory()->forProject($other)->create();
    }

    /** A client manager who sees only the fixture's project. */
    private function specificClient(string $role = 'client_project_manager'): User
    {
        $user = User::factory()->client($this->company, $role, false)->create();
        $user->projects()->attach($this->project);

        return $user;
    }

    // ------------------------------------------------------------ list, counters, details: their projects only

    public function test_the_client_lists_and_counts_the_trips_of_its_projects_only(): void
    {
        $mine = $this->createAwaiting();
        $this->otherProjectTrip();
        $client = $this->specificClient();

        $this->assertSame([$mine->id], collect($this->read($client, 'trips')->assertOk()->json('data'))->pluck('id')->all());

        $stats = $this->read($client, 'trips/stats')->assertOk()->json('data');
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, collect($stats['by_status'])->firstWhere('status', Trip::STATUS_AWAITING_CLIENT_APPROVAL)['count']);
    }

    public function test_another_projects_trip_is_a_404_everywhere(): void
    {
        $trip = $this->otherProjectTrip();
        $client = $this->specificClient();
        $this->verified($client);

        $this->read($client, "trips/{$trip->id}")->assertNotFound();
        $this->read($client, "trips/{$trip->id}/files/live-photo")->assertNotFound();
        $this->read($client, "trips/{$trip->id}/client-sub-services")->assertNotFound();
        $this->send($client, "trips/{$trip->id}/client-approve", ['use_saved_signature' => '1'])->assertNotFound();
        $this->send($client, "trips/{$trip->id}/client-reject", ['reason' => 'No'])->assertNotFound();
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_another_companys_client_sees_none_of_the_trips(): void
    {
        $trip = $this->createAwaiting();
        $stranger = User::factory()->client(null, 'client_project_manager')->create();

        $this->assertSame([], $this->read($stranger, 'trips')->assertOk()->json('data'));
        $this->read($stranger, "trips/{$trip->id}")->assertNotFound();
    }

    public function test_the_details_carry_the_client_buttons_and_the_default_approver(): void
    {
        $trip = $this->createAwaiting(['client_approver_id' => $this->clientManager->id]);

        $data = $this->read($this->clientManager, "trips/{$trip->id}")->assertOk()->json('data');

        $this->assertSame(['approve' => true, 'reject' => true], $data['client_actions']);
        $this->assertSame('Client Manager', $data['client_approver']['name']);
        $this->assertSame('Riyadh Hotel', $data['project']['name']);
        $this->assertArrayNotHasKey('actions', $data);
        $this->assertArrayNotHasKey('events', $data);
    }

    public function test_the_client_buttons_are_off_once_the_trip_left_awaiting(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());

        $this->assertSame(['approve' => false, 'reject' => false], $this->read($this->clientAuditor, "trips/{$trip->id}")->assertOk()->json('data.client_actions'));
    }

    public function test_the_list_shows_the_default_approver_without_a_query_per_row(): void
    {
        foreach (range(1, 3) as $i) {
            $this->createAwaiting(['client_approver_id' => $this->clientManager->id]);
        }

        $this->actingAs($this->clientManager, 'web');
        $this->getJson('/api/v1/trips')->assertOk(); // warms the role / permission cache
        DB::enableQueryLog();
        $rows = $this->getJson('/api/v1/trips')->assertOk()->json('data');
        $queries = count(DB::getQueryLog());

        $this->createAwaiting(['client_approver_id' => $this->clientManager->id]);
        DB::flushQueryLog();
        $this->getJson('/api/v1/trips')->assertOk();

        $this->assertSame('Client Manager', $rows[0]['client_approver']['name']);
        $this->assertSame($queries, count(DB::getQueryLog()), 'One more trip must not add queries.');
    }

    // ------------------------------------------------------------ ج18 on the list, the search and the export

    public function test_the_client_auditor_cannot_find_a_trip_by_its_po_number(): void
    {
        $this->createAwaiting();

        $this->assertSame([], $this->read($this->clientAuditor, 'trips?search=ALN-1200')->assertOk()->json('data'));
        $row = $this->read($this->clientAuditor, 'trips')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('purchase_order', $row);
        $this->assertArrayNotHasKey('trip_cost', $row);
    }

    public function test_the_client_manager_finds_a_trip_by_its_po_number(): void
    {
        $trip = $this->createAwaiting();

        $this->assertSame([$trip->id], collect($this->read($this->clientManager, 'trips?search=ALN-1200')->assertOk()->json('data'))->pluck('id')->all());
    }

    public function test_the_client_export_has_the_clients_columns_and_its_trips_only(): void
    {
        Excel::fake();
        $mine = $this->createAwaiting(['client_approver_id' => $this->clientManager->id]);
        $this->otherProjectTrip();
        $client = $this->specificClient('client_project_auditor');

        $this->actingAs($client, 'web')->get('/api/v1/trips/export?format=xlsx')->assertOk();

        Excel::assertDownloaded('trips.xlsx', function (TripsExport $export) use ($mine) {
            $this->assertSame(['Trip No.', 'Date', 'Driver', 'Vehicle', 'Project', 'Default client approver', 'Sub-service', 'Status', 'PO'], $export->headings());
            $this->assertSame([$mine->id], $export->collection()->pluck('id')->all());
            $row = $export->map($export->collection()->first());
            $this->assertSame('Client Manager', $row[5]);
            $this->assertSame(__('Linked to a PO'), $row[8], 'The auditor sees whether it is linked, not which PO.');

            return true;
        });
    }

    public function test_the_client_pdf_export_downloads(): void
    {
        $this->createAwaiting();

        $this->actingAs($this->clientManager, 'web')->get('/api/v1/trips/export?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_filters_list_the_clients_projects_and_services(): void
    {
        $this->createAwaiting();
        $this->otherProjectTrip();

        $data = $this->read($this->specificClient(), 'trips/client-filters')->assertOk()->json('data');

        $this->assertSame(['Riyadh Hotel'], array_column($data['projects'], 'name'));
        $this->assertSame(['Solid Waste Transport'], array_column($data['main_services'], 'name'));
        $this->assertSame(['General collection'], array_column($data['sub_services'], 'name'));
    }

    public function test_staff_have_no_client_filters(): void
    {
        $this->read($this->admin, 'trips/client-filters')->assertForbidden();
    }

    // ------------------------------------------------------------ files

    public function test_the_client_opens_the_live_photo_and_its_own_attachment(): void
    {
        $trip = $this->createByDriver();
        $this->clientReject($trip);
        $trip->refresh();
        $trip->forceFill(['client_attachment_path' => tap('trips/'.$trip->id.'/attachment/a.pdf', fn ($p) => Storage::disk('local')->put($p, '%PDF-1.4'))])->save();

        $this->read($this->clientManager, "trips/{$trip->id}/files/live-photo")->assertOk();
        $this->read($this->clientManager, "trips/{$trip->id}/files/client-attachment")->assertOk();
    }

    public function test_the_client_cannot_open_signatures_or_staff_documents(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());

        $this->read($this->clientManager, "trips/{$trip->id}/files/client-signature")->assertForbidden();
        $this->read($this->clientManager, "trips/{$trip->id}/files/delivery-note")->assertForbidden();
    }

    // ------------------------------------------------------------ approve

    public function test_approving_needs_the_emailed_code(): void
    {
        $trip = $this->createAwaiting();

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", ['signature' => $this->image('s.png')])
            ->assertForbidden()->assertJsonPath('code', 'otp_required');
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_the_client_approves_with_a_drawn_signature_and_keeps_it_on_its_profile(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", [
            'signature' => $this->image('drawn.png'),
            'save_signature' => 'true',
            'comment' => 'All good',
            'attachment' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.status', Trip::STATUS_CLIENT_APPROVED)->assertJsonPath('data.client_comment', 'All good');

        $trip->refresh();
        $this->assertSame($this->clientManager->id, $trip->client_approved_by);
        $this->assertNotNull($trip->client_signature_path);
        $this->assertNotNull($trip->client_attachment_path);
        $this->assertNotNull($this->clientManager->fresh()->signature_image, '"Save my signature" keeps it on the account.');
        $this->assertNotSame($trip->client_signature_path, $this->clientManager->fresh()->signature_image, 'The trip holds its own copy.');
    }

    public function test_the_drawn_signature_stays_off_the_profile_unless_asked(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientAuditor);

        $this->send($this->clientAuditor, "trips/{$trip->id}/client-approve", ['signature' => $this->image('drawn.png')])->assertOk();

        $this->assertNull($this->clientAuditor->fresh()->signature_image);
    }

    public function test_the_client_approves_with_its_saved_signature_and_an_uploaded_stamp(): void
    {
        $saved = 'user-signatures/'.$this->clientManager->id.'/sig.png';
        Storage::disk('local')->put($saved, 'png');
        $this->clientManager->forceFill(['signature_image' => $saved])->save();
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", [
            'use_saved_signature' => '1',
            'stamp' => $this->image('stamp.png'),
        ])->assertOk();

        $trip->refresh();
        $this->assertNotNull($trip->client_signature_path);
        $this->assertNotNull($trip->client_stamp_path, 'The uploaded stamp is snapshotted on the trip.');
        $this->assertNull($this->clientManager->fresh()->stamp_image, 'An uploaded stamp is never put on the account.');
    }

    public function test_approving_without_a_signature_is_refused(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", [])->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_a_signature_that_is_not_an_image_is_refused(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        // A real file (not UploadedFile::fake(), whose MIME type follows the name): the type is read from the bytes.
        $path = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", [
            'signature' => new UploadedFile($path, 'sig.png', 'image/png', null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_approving_changes_nothing_but_the_sub_service(): void
    {
        $trip = $this->createAwaiting(['quantity_ton' => 2]);
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", [
            'signature' => $this->image('drawn.png'),
            'trip_cost' => '1',
            'quantity_ton' => '0.5',
            'project_id' => (string) Project::factory()->for($this->company)->create()->id,
            'status' => Trip::STATUS_COMPLETED,
            'intermediate_facility_id' => '',
        ])->assertOk();

        $trip->refresh();
        $this->assertEquals(100, $trip->trip_cost);
        $this->assertEquals(2, $trip->quantity_ton);
        $this->assertSame($this->project->id, $trip->project_id);
        $this->assertSame(Trip::STATUS_CLIENT_APPROVED, $trip->status);
        $this->assertSame($this->facility->id, $trip->intermediate_facility_id);
    }

    public function test_the_client_switches_to_a_sub_service_that_suits_the_container(): void
    {
        $fit = SubService::factory()->forService($this->service)->create(['name' => 'Wood collection']);
        $fit->facilities()->attach($this->facility);
        $this->makeCompatible(null, $this->vehicle, null, [$fit], [$this->returningAsset]);
        $unfit = SubService::factory()->forService($this->service)->create(['name' => 'Tank wash']);
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $names = array_column($this->read($this->clientManager, "trips/{$trip->id}/client-sub-services")->assertOk()->json('data'), 'name');
        $this->assertContains('Wood collection', $names);
        $this->assertContains('General collection', $names);
        $this->assertNotContains('Tank wash', $names);

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", ['signature' => $this->image('d.png'), 'sub_service_id' => (string) $unfit->id])
            ->assertUnprocessable()->assertJsonValidationErrors('sub_service_id');

        $this->send($this->clientManager, "trips/{$trip->id}/client-approve", ['signature' => $this->image('d.png'), 'sub_service_id' => (string) $fit->id])
            ->assertOk()->assertJsonPath('data.sub_service.name', 'Wood collection');

        $this->assertSame(['from' => 'General collection', 'to' => 'Wood collection'], TripEvent::where('type', TripEvent::CLIENT_APPROVED)->sole()->changes['sub_service']);
    }

    public function test_the_client_auditor_approves_too(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientAuditor);

        $this->send($this->clientAuditor, "trips/{$trip->id}/client-approve", ['signature' => $this->image('d.png')])
            ->assertOk()->assertJsonPath('data.status', Trip::STATUS_CLIENT_APPROVED);
    }

    public function test_a_trip_no_longer_awaiting_is_a_409(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());
        $this->verified($this->clientAuditor);

        $this->send($this->clientAuditor, "trips/{$trip->id}/client-approve", ['signature' => $this->image('d.png')])->assertStatus(409);
    }

    public function test_staff_cannot_approve_for_the_client(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->admin);

        $this->send($this->admin, "trips/{$trip->id}/client-approve", ['signature' => $this->image('d.png')])->assertForbidden();
    }

    public function test_the_client_cannot_use_the_staff_changes(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->actingAs($this->clientManager, 'web')->patchJson("/api/v1/trips/{$trip->id}", ['quantity_ton' => 1])->assertForbidden();
        $this->send($this->clientManager, "trips/{$trip->id}/cancel")->assertForbidden();
        $this->send($this->clientManager, 'trips', $this->staffData())->assertForbidden();
    }

    // ------------------------------------------------------------ reject

    public function test_rejecting_needs_the_reasons(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-reject", ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_rejecting_needs_the_emailed_code(): void
    {
        $trip = $this->createAwaiting();

        $this->send($this->clientManager, "trips/{$trip->id}/client-reject", ['reason' => 'Wrong'])->assertForbidden()->assertJsonPath('code', 'otp_required');
    }

    public function test_the_client_rejects_with_the_reasons_and_a_file(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-reject", [
            'reason' => 'The container was half empty',
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
            'sub_service_id' => (string) $this->receiptSub->id,
        ])->assertOk()->assertJsonPath('data.status', Trip::STATUS_CLIENT_ISSUE)->assertJsonPath('data.client_comment', 'The container was half empty');

        $trip->refresh();
        $this->assertNotNull($trip->client_attachment_path);
        $this->assertSame($this->sub->id, $trip->sub_service_id, 'A sub-service chosen on the page is not kept on reject (ج7).');
    }

    public function test_an_attachment_of_another_type_is_refused(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->clientManager);

        $this->send($this->clientManager, "trips/{$trip->id}/client-reject", [
            'reason' => 'Bad',
            'attachment' => UploadedFile::fake()->createWithContent('x.html', '<script>alert(1)</script>'),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    }
}
