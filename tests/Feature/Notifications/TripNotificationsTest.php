<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Support\NotificationType;
use App\Domain\Trips\Actions\AttachRecycleReceiptByDriverAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserNotification;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.2 — who is told about what (the "الدور الذي يظهر عنده الاشعار" column of the FRD table), straight from a trip's
 * actions: the right audience for each of the six trip notifications, nobody notified of their own action, nobody
 * who is deactivated, and nothing across tenants. The contractor user owns the fixture's driver.
 */
class TripNotificationsTest extends TripTestCase
{
    private User $contractorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $contractor = Contractor::factory()->create();
        Driver::whereKey($this->driver->id)->update(['contractor_id' => $contractor->id]);
        $this->contractorUser = User::factory()->contractorUser($contractor)->create();
    }

    /** @return list<string> the notified users' emails for `$type`, sorted */
    private function who(string $type): array
    {
        return UserNotification::query()->withoutGlobalScopes()->where('type', $type)->get()
            ->map(fn ($n) => User::query()->withoutGlobalScopes()->find($n->user_id)->email)->sort()->values()->all();
    }

    private function emails(User ...$users): array
    {
        return collect($users)->map->email->sort()->values()->all();
    }

    // ── a new trip that needs the client's approval ─────────────────

    public function test_a_trip_made_by_staff_tells_the_clients_approver_and_the_contractor_not_the_staff(): void
    {
        $this->createAwaiting(); // by the admin

        // the default approver (the project's representative), not every client account; the contractor of the driver
        $this->assertSame($this->emails($this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_CREATED));
    }

    public function test_a_trip_made_by_the_driver_also_tells_the_admin_and_data_entry(): void
    {
        $this->createByDriver();

        $this->assertSame(
            $this->emails($this->admin, $this->dataEntry, $this->clientManager, $this->contractorUser),
            $this->who(NotificationType::TRIP_CREATED),
        );
    }

    public function test_without_the_default_approver_every_client_account_that_can_act_is_told(): void
    {
        $this->project->forceFill(['representative_id' => null])->save();

        $this->createAwaiting();

        $this->assertSame($this->emails($this->clientAuditor, $this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_CREATED));
    }

    public function test_the_auditor_and_the_driver_are_never_notified(): void
    {
        $this->createByDriver();
        $this->clientApprove($this->tripIn(Trip::STATUS_AWAITING_CLIENT_APPROVAL));

        $this->assertSame(0, UserNotification::query()->withoutGlobalScopes()->whereIn('user_id', [$this->auditor->id, $this->driver->user->id])->count());
    }

    // ── the client's decision ───────────────────────────────────────

    public function test_the_clients_approval_tells_the_staff_but_not_the_client_who_did_it(): void
    {
        $this->clientApprove($this->createAwaiting());

        $this->assertSame($this->emails($this->admin, $this->dataEntry), $this->who(NotificationType::TRIP_CLIENT_APPROVED));
    }

    public function test_the_clients_rejection_tells_the_staff_only_when_the_driver_made_the_trip(): void
    {
        $this->clientReject($this->createAwaiting()); // made by staff
        $this->assertSame([], $this->who(NotificationType::TRIP_CLIENT_REJECTED));

        $this->clientReject($this->createByDriver());
        $this->assertSame($this->emails($this->admin, $this->dataEntry), $this->who(NotificationType::TRIP_CLIENT_REJECTED));
    }

    // ── the driver adds the receipt ─────────────────────────────────

    public function test_the_recycle_receipt_tells_the_staff_when_the_driver_made_the_trip(): void
    {
        $driverTrip = $this->createByDriver();
        app(AttachRecycleReceiptByDriverAction::class)->execute($driverTrip, $this->driver->user, $this->image('receipt.png'));

        $this->assertSame($this->emails($this->admin, $this->dataEntry), $this->who(NotificationType::TRIP_UPDATED));
    }

    // ── final approval and cancellation ─────────────────────────────

    public function test_the_final_approval_tells_every_client_account_and_the_contractor_not_the_staff(): void
    {
        $this->finalApprove($this->clientApprove($this->createAwaiting()));

        $this->assertSame($this->emails($this->clientAuditor, $this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_FINAL_APPROVED));
    }

    public function test_a_trip_recorded_as_completed_straight_away_is_a_final_approval_for_them_too(): void
    {
        app(CreateTripByStaffAction::class)->execute($this->admin, $this->staffData(['requires_client_approval' => false]), [], $this->completion());

        $this->assertSame($this->emails($this->clientAuditor, $this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_FINAL_APPROVED));
        $this->assertSame([], $this->who(NotificationType::TRIP_CREATED));
    }

    public function test_the_cancellation_tells_every_client_account_and_the_contractor(): void
    {
        $this->cancel($this->createAwaiting());

        $this->assertSame($this->emails($this->clientAuditor, $this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_CANCELLED));
    }

    // ── who never receives one ──────────────────────────────────────

    public function test_a_deactivated_account_is_not_notified_and_one_on_vacation_is(): void
    {
        $this->clientAuditor->forceFill(['status' => 'deactivated'])->save();
        $this->clientManager->forceFill(['status' => 'on_vacation'])->save();

        $this->cancel($this->createAwaiting());

        $this->assertSame($this->emails($this->clientManager, $this->contractorUser), $this->who(NotificationType::TRIP_CANCELLED));
    }

    public function test_another_contractors_user_and_another_tenants_staff_are_not_notified(): void
    {
        $otherContractorUser = User::factory()->contractorUser(Contractor::factory()->create())->create();

        $this->createByDriver();

        $this->assertNotContains($otherContractorUser->email, $this->who(NotificationType::TRIP_CREATED));

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $outsider = User::factory()->create();
        $outsider->assignRole('system_admin');
        app()->instance('tenant', $this->tenant);

        $this->createByDriver();

        $this->assertNotContains($outsider->email, $this->who(NotificationType::TRIP_CREATED));
    }

    public function test_the_notification_carries_the_trip_code_and_no_cost(): void
    {
        $trip = $this->createByDriver();

        $row = UserNotification::query()->withoutGlobalScopes()->where('user_id', $this->clientManager->id)->firstOrFail();

        $this->assertSame($trip->id, $row->trip_id);
        $this->assertSame(['code' => $trip->code], $row->data);
        $this->assertSame($trip->code, $row->subject);
    }
}
