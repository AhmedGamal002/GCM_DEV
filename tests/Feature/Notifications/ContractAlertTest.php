<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Support\ContractAlertNotifier;
use App\Domain\Notifications\Support\NotificationType;
use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Models\User;
use App\Models\UserNotification;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.2 — the contract alert (decision ب9): everyone who may create POs is told once when a PO line gets to
 * "about to run out" and once when it is exhausted — caused by a trip or by the calendar — and again only after it
 * recovered and ran low again. The System Admin is the only account that may create POs today.
 */
class ContractAlertTest extends TripTestCase
{
    private function alerts(string $type): int
    {
        return UserNotification::query()->withoutGlobalScopes()->where('type', $type)->count();
    }

    private function adminAlerts(): array
    {
        return UserNotification::query()->withoutGlobalScopes()->whereIn('type', [NotificationType::PO_NEAR_EXHAUSTION, NotificationType::PO_EXHAUSTED])
            ->pluck('user_id')->unique()->values()->all();
    }

    public function test_a_line_running_low_notifies_the_admin_once_and_only_the_admin(): void
    {
        $this->assertSame(1, ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION));
        $this->assertSame(0, ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION), 'the same status is not notified twice');

        $this->assertSame([$this->admin->id], $this->adminAlerts());
        $this->assertSame(1, $this->alerts(NotificationType::PO_NEAR_EXHAUSTION));
    }

    public function test_exhaustion_is_its_own_alert(): void
    {
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION);
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::EXHAUSTED);

        $this->assertSame(1, $this->alerts(NotificationType::PO_NEAR_EXHAUSTION));
        $this->assertSame(1, $this->alerts(NotificationType::PO_EXHAUSTED));
    }

    public function test_a_line_that_recovered_and_runs_low_again_is_notified_again(): void
    {
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION);
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::VALID);
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION);

        $this->assertSame(2, $this->alerts(NotificationType::PO_NEAR_EXHAUSTION));
    }

    public function test_a_deactivated_po_notifies_nobody(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();

        $this->assertSame(0, ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::EXHAUSTED));
    }

    public function test_the_trips_that_use_a_line_up_raise_the_alerts(): void
    {
        $this->line->forceFill(['max_trips' => 2, 'alert_percent' => 60])->save();

        $this->createAwaiting();
        $this->assertSame(1, $this->alerts(NotificationType::PO_NEAR_EXHAUSTION), 'one of two trips left = 50%, under the 60% line');

        $this->createAwaiting();
        $this->assertSame(1, $this->alerts(NotificationType::PO_EXHAUSTED));
    }

    public function test_the_alert_names_the_contract_and_the_service(): void
    {
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::NEAR_EXHAUSTION);

        $row = UserNotification::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->po->id, $row->purchase_order_id);
        $this->assertSame('ALN-1200', $row->subject);
        $this->assertSame('ALN-1200', $row->data['po']);
        $this->assertSame('Solid Waste Transport', $row->data['service']);
    }

    // ── the calendar ────────────────────────────────────────────────

    public function test_the_daily_check_catches_a_line_whose_dates_ran_low_and_notifies_once(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 29)->setTime(10, 0)); // the PO ends on 30 May: the date part is in its alert zone

        $this->artisan('notifications:check-purchase-orders')->assertSuccessful();
        $this->artisan('notifications:check-purchase-orders')->assertSuccessful();

        $this->assertSame(1, $this->alerts(NotificationType::PO_NEAR_EXHAUSTION));
        $this->assertSame([$this->admin->id], $this->adminAlerts());
    }

    public function test_the_daily_check_says_nothing_about_a_line_in_good_shape(): void
    {
        $this->artisan('notifications:check-purchase-orders')->assertSuccessful();

        $this->assertSame(0, UserNotification::query()->withoutGlobalScopes()->count());
    }

    public function test_a_user_who_cannot_create_pos_is_not_an_alert_recipient(): void
    {
        ContractAlertNotifier::sync($this->line, PurchaseOrderStatus::EXHAUSTED);

        $notified = UserNotification::query()->withoutGlobalScopes()->pluck('user_id')->all();

        foreach ([$this->dataEntry, $this->auditor, $this->clientManager] as $user) {
            $this->assertNotContains($user->id, $notified);
        }
        $this->assertInstanceOf(User::class, $this->admin);
    }
}
