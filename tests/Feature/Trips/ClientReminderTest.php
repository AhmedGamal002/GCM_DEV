<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Mail\ClientApprovalReminderMail;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Part 7.3 — "remind the client to approve" (FRD V01.14 trip details, decision ج17): who is mailed, when it is
 * refused, the one-hour gap read from the trip's own history, who may press the button, and that it needs no code.
 * One test = one actor (the sanctum guard caches the first user of a test).
 */
class ClientReminderTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function remind(Trip $trip, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->admin, 'web')->postJson("/api/v1/trips/{$trip->id}/remind-client");
    }

    // ── the happy path ──────────────────────────────────────────────

    public function test_staff_remind_the_default_approver_and_the_reminder_is_written_to_the_history(): void
    {
        $trip = $this->createAwaiting();

        $this->remind($trip)
            ->assertOk()
            ->assertJsonPath('data.sent_to', 1)
            ->assertJsonPath('data.next_reminder_at', now()->addMinutes(60)->toIso8601String());

        // only the project's representative (the trip's default approver), not the project auditor too
        Mail::assertSent(ClientApprovalReminderMail::class, 1);
        Mail::assertSent(ClientApprovalReminderMail::class, fn (ClientApprovalReminderMail $m) => $m->hasTo($this->clientManager->email));

        $event = TripEvent::where('trip_id', $trip->id)->where('type', TripEvent::CLIENT_REMINDED)->sole();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame([['id' => $this->clientManager->id, 'name' => 'Client Manager']], $event->changes['recipients']);
    }

    public function test_data_entry_may_press_it_too(): void
    {
        $this->remind($this->createAwaiting(), $this->dataEntry)->assertOk();
    }

    public function test_it_asks_for_no_verification_code(): void
    {
        // the admin has never entered a one-time code in this test (ج17: the reminder needs none)
        $this->remind($this->createAwaiting())->assertOk();
    }

    public function test_the_mail_names_the_trip_and_the_project_and_links_to_the_trip_but_shows_no_cost(): void
    {
        $trip = $this->createAwaiting(['trip_cost' => 777.5]);

        $html = (new ClientApprovalReminderMail($trip, $this->clientManager, url('/app/trip/view/'.$trip->id)))->render();

        $this->assertStringContainsString($trip->code, $html);
        $this->assertStringContainsString('Riyadh Hotel', $html);
        $this->assertStringContainsString('/app/trip/view/'.$trip->id, $html);
        $this->assertStringContainsString('Client Manager', $html);
        $this->assertStringNotContainsString('777', $html, 'the client never sees the cost (ج18)');
    }

    public function test_the_mail_is_in_arabic_when_asked_in_arabic(): void
    {
        $trip = $this->createAwaiting();

        app()->setLocale('ar');
        $html = (new ClientApprovalReminderMail($trip, $this->clientManager, 'https://gcm.test/app/trip/view/1'))->render();
        app()->setLocale('en');

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('فتح الرحلة', $html);
    }

    // ── who is mailed ───────────────────────────────────────────────

    public function test_when_the_default_approver_cannot_act_every_client_account_that_can_is_mailed(): void
    {
        $trip = $this->createAwaiting();
        // the representative goes on vacation: the project auditor is the only one left who can act
        $this->clientManager->forceFill(['status' => 'on_vacation'])->save();
        $this->clientAuditor->forceFill(['status' => 'active'])->save();

        $this->remind($trip)->assertOk()->assertJsonPath('data.sent_to', 1);

        Mail::assertSent(ClientApprovalReminderMail::class, fn (ClientApprovalReminderMail $m) => $m->hasTo($this->clientAuditor->email));
        Mail::assertNotSent(ClientApprovalReminderMail::class, fn (ClientApprovalReminderMail $m) => $m->hasTo($this->clientManager->email));
    }

    public function test_with_no_default_approver_all_active_client_accounts_of_the_project_are_mailed(): void
    {
        $trip = $this->createAwaiting();
        $trip->forceFill(['client_approver_id' => null])->save();

        $this->remind($trip)->assertOk()->assertJsonPath('data.sent_to', 2);

        Mail::assertSent(ClientApprovalReminderMail::class, 2);
    }

    public function test_a_client_account_that_does_not_see_the_project_is_not_mailed(): void
    {
        $trip = $this->createAwaiting();
        $trip->forceFill(['client_approver_id' => null])->save();
        // the project auditor is assigned to other projects only
        $this->clientAuditor->forceFill(['all_projects' => false])->save();

        $this->remind($trip)->assertOk()->assertJsonPath('data.sent_to', 1);

        Mail::assertNotSent(ClientApprovalReminderMail::class, fn (ClientApprovalReminderMail $m) => $m->hasTo($this->clientAuditor->email));
    }

    public function test_with_nobody_able_to_approve_it_is_refused_and_nothing_is_recorded(): void
    {
        $trip = $this->createAwaiting();
        $this->clientManager->forceFill(['status' => 'deactivated'])->save();
        $this->clientAuditor->forceFill(['status' => 'deactivated'])->save();

        $this->remind($trip)->assertUnprocessable()->assertJsonPath('code', 'reminder_no_recipient');

        Mail::assertNothingSent();
        $this->assertSame(0, TripEvent::where('type', TripEvent::CLIENT_REMINDED)->count());
    }

    // ── the one-hour gap ────────────────────────────────────────────

    public function test_a_second_reminder_within_the_hour_is_refused_with_the_minutes_left(): void
    {
        $trip = $this->createAwaiting();
        $this->remind($trip)->assertOk();

        $this->travel(20)->minutes();

        $this->remind($trip)
            ->assertStatus(429)
            ->assertJsonPath('code', 'reminder_too_soon')
            ->assertJsonPath('retry_after_minutes', 40)
            ->assertHeader('Retry-After', '2400');

        Mail::assertSent(ClientApprovalReminderMail::class, 1);
        $this->assertSame(1, TripEvent::where('type', TripEvent::CLIENT_REMINDED)->count());
    }

    public function test_after_the_hour_another_one_goes_out(): void
    {
        $trip = $this->createAwaiting();
        $this->remind($trip)->assertOk();

        $this->travel(61)->minutes();

        $this->remind($trip)->assertOk();
        Mail::assertSent(ClientApprovalReminderMail::class, 2);
        $this->assertSame(2, TripEvent::where('type', TripEvent::CLIENT_REMINDED)->count());
    }

    public function test_the_gap_belongs_to_the_trip_not_to_the_person_who_pressed_it(): void
    {
        $trip = $this->createAwaiting();
        $this->remind($trip, $this->admin)->assertOk();
        $this->travel(5)->minutes();

        $this->remind($trip, $this->dataEntry)->assertStatus(429);
    }

    public function test_another_trip_has_its_own_gap(): void
    {
        $first = $this->createAwaiting();
        $second = $this->createAwaiting();
        $this->remind($first)->assertOk();

        $this->remind($second)->assertOk();
    }

    public function test_the_interval_comes_from_the_config(): void
    {
        config(['trips.reminder_interval_minutes' => 10]);
        $trip = $this->createAwaiting();
        $this->remind($trip)->assertOk();

        $this->travel(11)->minutes();

        $this->remind($trip)->assertOk();
    }

    // ── which trips ─────────────────────────────────────────────────

    #[DataProvider('statusesThatCannotBeReminded')]
    public function test_only_a_trip_awaiting_the_clients_approval_can_be_reminded(string $status): void
    {
        $trip = $this->tripIn($status);

        $this->remind($trip)->assertUnprocessable()->assertJsonPath('code', 'reminder_not_awaiting');

        Mail::assertNothingSent();
    }

    public static function statusesThatCannotBeReminded(): array
    {
        return [
            [Trip::STATUS_CLIENT_APPROVED],
            [Trip::STATUS_CLIENT_ISSUE],
            [Trip::STATUS_COMPLETED],
            [Trip::STATUS_CANCELLED],
        ];
    }

    // ── who may press it ────────────────────────────────────────────

    public function test_the_auditor_cannot(): void
    {
        $trip = $this->createAwaiting();

        $this->remind($trip, $this->auditor)->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_a_client_manager_cannot_remind_themselves(): void
    {
        $trip = $this->createAwaiting();

        $this->remind($trip, $this->clientManager)->assertForbidden();
    }

    public function test_a_driver_cannot(): void
    {
        $trip = $this->createAwaiting();

        $this->remind($trip, $this->driver->user)->assertForbidden();
    }

    public function test_a_guest_cannot(): void
    {
        $trip = $this->createAwaiting();

        $this->postJson("/api/v1/trips/{$trip->id}/remind-client")->assertUnauthorized();
    }

    public function test_it_works_with_a_real_bearer_token(): void
    {
        $trip = $this->createAwaiting();
        $token = $this->admin->createToken('postman')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/trips/{$trip->id}/remind-client")->assertOk();
    }

    // ── errors and isolation ────────────────────────────────────────

    public function test_when_the_mail_cannot_be_sent_nothing_is_recorded_and_the_button_works_again_at_once(): void
    {
        $trip = $this->createAwaiting();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp is down'));
        $this->remind($trip)->assertStatus(503)->assertJsonPath('code', 'reminder_mail_failed');

        $this->assertSame(0, TripEvent::where('type', TripEvent::CLIENT_REMINDED)->count());
    }

    public function test_an_unknown_trip_is_a_404(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/trips/999999/remind-client')->assertNotFound();
    }

    public function test_a_trip_of_another_tenant_is_a_404(): void
    {
        $trip = $this->createAwaiting();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $theirAdmin = User::factory()->create();
        $theirAdmin->assignRole('system_admin');

        $this->remind($trip, $theirAdmin)->assertNotFound();
        Mail::assertNothingSent();
    }
}
