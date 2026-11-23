<?php

namespace Tests\Feature\Trips;

use App\Models\MainService;
use App\Models\OtpChallenge;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Part 7.4 — GCM's trip changes through the API: create (needing the
 * client's approval, or completed directly — no OTP, ج15), edit (status
 * kept, ج9), the final approval, the client's objection accepted / rejected,
 * cancelling. The final approval, rejecting the objection and cancelling need
 * the e-mailed code (403 otp_required); the rules of each change are the
 * actions' (tested in 7.1) — here: the HTTP shape, who, the OTP, the answers.
 *
 * One actor per test method (the actingAs cache trap, CLAUDE.md).
 */
class TripApiWriteTest extends TripTestCase
{
    /** This login entered the e-mailed code (an API request without a session is scoped to the user). */
    private function verified(User $user, ?string $scope = null): void
    {
        OtpChallenge::create([
            'user_id' => $user->id,
            'scope' => $scope ?? 'user:'.$user->id,
            'code_hash' => str_repeat('a', 64),
            'attempts' => 0,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);
    }

    private function send(User $user, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user, 'web')->post('/api/v1/'.$uri, $data, ['Accept' => 'application/json']);
    }

    /** The staff form as a browser sends it: multipart, every value a string. */
    private function form(array $overrides = []): array
    {
        return array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $this->staffData([
            'quantity_ton' => '3.75',
            ...$overrides,
        ]));
    }

    private function signature(): UploadedFile
    {
        return UploadedFile::fake()->image('signature.png', 300, 100);
    }

    // ------------------------------------------------------------ create

    public function test_staff_create_a_trip_awaiting_the_client(): void
    {
        $response = $this->send($this->dataEntry, 'trips', $this->form())->assertCreated();

        $this->assertSame('awaiting_client_approval', $response->json('data.status'));
        $this->assertSame('ALN-T0001', $response->json('data.code'));
        $this->assertSame($this->dataEntry->id, $response->json('data.gcm_approver.id'), 'FRD: the default GCM approver is the creator.');
        $this->assertSame(1, $this->tripsUsed(), 'The quota is charged at creation (ج1).');
    }

    public function test_a_trip_created_completed_needs_the_signatures_but_no_code(): void
    {
        $data = $this->form(['requires_client_approval' => false]);

        $this->send($this->admin, 'trips', $data)->assertStatus(422)->assertJsonValidationErrors('gcm_signature');

        $response = $this->send($this->admin, 'trips', [
            ...$data,
            'client_signature' => $this->signature(),
            'client_signer_name' => 'Site engineer',
            'gcm_signature' => $this->signature(),
        ])->assertCreated();

        $this->assertSame('completed', $response->json('data.status'));
    }

    public function test_the_frd_required_fields_of_the_staff_form(): void
    {
        $this->send($this->admin, 'trips', $this->form(['sub_service_id' => null, 'intermediate_facility_id' => null, 'quantity_ton' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sub_service_id', 'intermediate_facility_id', 'quantity_ton']);

        $this->send($this->admin, 'trips', $this->form(['requires_purchase_order' => false, 'purchase_order_id' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('trip_cost');
    }

    public function test_a_supplying_trip_needs_neither_quantity_nor_facility(): void
    {
        $supplying = MainService::factory()->supplying()->create();
        $sub = SubService::factory()->forService($supplying)->create(['asset_type' => 'container']);
        $this->makeCompatible(null, $this->vehicle, $supplying, [$sub], [$this->goingAsset, $this->returningAsset]);

        $this->send($this->admin, 'trips', $this->form([
            'main_service_id' => $supplying->id, 'sub_service_id' => $sub->id, 'intermediate_facility_id' => null, 'quantity_ton' => null,
            'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => '40',
        ]))->assertCreated()->assertJsonPath('data.quantity_ton', null);
    }

    public function test_the_actions_rules_come_back_as_422_on_the_form_field(): void
    {
        $this->vehicle->forceFill(['operational_status' => 'on_maintenance'])->save();

        $this->send($this->admin, 'trips', $this->form())->assertStatus(422)->assertJsonValidationErrors('vehicle_id');
    }

    public function test_the_auditor_cannot_create(): void
    {
        $this->send($this->auditor, 'trips', $this->form())->assertForbidden();
    }

    public function test_a_driver_cannot_use_the_staff_create(): void
    {
        $this->send($this->driver->user, 'trips', $this->form())->assertForbidden();
    }

    public function test_a_bad_file_is_refused_before_anything_is_saved(): void
    {
        $this->send($this->admin, 'trips', [...$this->form(), 'delivery_note' => UploadedFile::fake()->create('note.html', 10, 'text/html')])
            ->assertStatus(422)->assertJsonValidationErrors('delivery_note');

        $this->assertSame(0, Trip::count());
    }

    // ------------------------------------------------------------ edit

    public function test_an_edit_keeps_the_status_and_only_changes_what_is_sent(): void
    {
        $trip = $this->tripIn(Trip::STATUS_CLIENT_APPROVED);

        $response = $this->send($this->dataEntry, "trips/{$trip->id}", ['_method' => 'PATCH', 'quantity_ton' => '2', 'delivery_note_number' => 'DN-77'])->assertOk();

        $this->assertSame('client_approved', $response->json('data.status'), 'ج9: an edit doesn\'t move the status.');
        $this->assertEquals(2, $response->json('data.quantity_ton'));
        $this->assertSame('DN-77', $response->json('data.delivery_note_number'));
        $this->assertSame($this->facility->id, $response->json('data.facility.id'), 'Unsent fields stay.');
    }

    public function test_a_cancelled_trip_cannot_be_edited(): void
    {
        $trip = $this->cancel($this->createAwaiting());

        $this->send($this->admin, "trips/{$trip->id}", ['_method' => 'PATCH', 'quantity_ton' => '1'])
            ->assertStatus(409)->assertJsonPath('code', 'trip_transition_not_allowed');
    }

    public function test_the_auditor_cannot_edit(): void
    {
        $trip = $this->createAwaiting();

        $this->send($this->auditor, "trips/{$trip->id}", ['_method' => 'PATCH', 'quantity_ton' => '1'])->assertForbidden();
    }

    // ------------------------------------------------------------ final approval (OTP)

    public function test_the_final_approval_needs_the_code(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());

        $this->send($this->admin, "trips/{$trip->id}/final-approve", ['gcm_signature' => $this->signature()])
            ->assertForbidden()->assertJsonPath('code', 'otp_required');

        $this->assertSame('client_approved', $trip->fresh()->status);
    }

    public function test_with_the_code_the_final_approval_completes_the_trip(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());
        $this->verified($this->admin);

        $this->send($this->admin, "trips/{$trip->id}/final-approve", ['gcm_signature' => $this->signature(), 'save_gcm_signature' => 'true'])
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($this->admin->fresh()->signature_image, 'د5: "keep it on my profile".');
    }

    public function test_gcm_may_approve_finally_without_the_client_uploading_its_signature(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->dataEntry);

        $this->send($this->dataEntry, "trips/{$trip->id}/final-approve", ['gcm_signature' => $this->signature()])
            ->assertStatus(422)->assertJsonValidationErrors('client_signature');

        $this->send($this->dataEntry, "trips/{$trip->id}/final-approve", [
            'gcm_signature' => $this->signature(),
            'client_signature' => $this->signature(),
            'client_signer_name' => 'Site engineer',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_it_works_with_a_real_bearer_token(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());
        $token = $this->admin->createToken('postman');
        $this->verified($this->admin, 'token:'.$token->accessToken->getKey());

        $this->withToken($token->plainTextToken)
            ->post("/api/v1/trips/{$trip->id}/final-approve", ['gcm_signature' => $this->signature()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }

    // ------------------------------------------------------------ the client's objection

    public function test_accepting_the_objection_needs_no_code_and_sends_it_back_to_the_client(): void
    {
        $trip = $this->clientReject($this->createAwaiting());

        $this->send($this->admin, "trips/{$trip->id}/accept-objection", ['quantity_ton' => '1'])
            ->assertOk()->assertJsonPath('data.status', 'awaiting_client_approval');
    }

    public function test_rejecting_the_objection_needs_the_code_and_completes(): void
    {
        $trip = $this->clientReject($this->createAwaiting());

        $this->send($this->admin, "trips/{$trip->id}/reject-objection", ['gcm_signature' => $this->signature()])
            ->assertForbidden()->assertJsonPath('code', 'otp_required');

        $this->verified($this->admin);
        $this->send($this->admin, "trips/{$trip->id}/reject-objection", [
            'gcm_signature' => $this->signature(),
            'client_signature' => $this->signature(),
            'client_signer_name' => 'Site engineer',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_an_objection_action_on_a_trip_without_objection_is_409(): void
    {
        $trip = $this->createAwaiting();

        $this->send($this->admin, "trips/{$trip->id}/accept-objection")->assertStatus(409);
    }

    // ------------------------------------------------------------ cancel (OTP)

    public function test_cancelling_needs_the_code_gives_the_quota_back_and_keeps_the_reason(): void
    {
        $trip = $this->createAwaiting();

        $this->send($this->dataEntry, "trips/{$trip->id}/cancel", ['reason' => 'Duplicate'])->assertForbidden();

        $this->verified($this->dataEntry);
        $response = $this->send($this->dataEntry, "trips/{$trip->id}/cancel", ['reason' => 'Duplicate'])->assertOk();

        $this->assertSame('cancelled', $response->json('data.status'));
        $this->assertSame('Duplicate', $response->json('data.cancellation_reason'));
        $this->assertSame(0, $this->tripsUsed(), 'ج1: cancelling gives the quota back.');
    }

    public function test_the_auditor_cannot_cancel_even_with_a_code(): void
    {
        $trip = $this->createAwaiting();
        $this->verified($this->auditor);

        $this->send($this->auditor, "trips/{$trip->id}/cancel")->assertForbidden();
    }
}
