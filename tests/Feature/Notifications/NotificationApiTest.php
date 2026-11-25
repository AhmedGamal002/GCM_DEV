<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Support\NotificationType;
use App\Domain\Notifications\Support\Notifier;
use App\Models\Contractor;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.2 — the notifications API: a user reads only their own (the bell's latest five, the page's filtered and
 * searched list), marks only their own as read, sees the sentence in the language of the app, and the link goes to
 * the page of their role. One actor per test (the sanctum guard caches the first user).
 */
class NotificationApiTest extends TripTestCase
{
    private function give(User $user, string $type = NotificationType::TRIP_CANCELLED, array $data = ['code' => 'ALN-T0001'], ?int $tripId = null, ?string $subject = 'ALN-T0001', ?string $at = null): void
    {
        Notifier::send($this->tenant->id, $type, collect([$user]), $data, tripId: $tripId, subject: $subject);

        if ($at !== null) {
            UserNotification::query()->withoutGlobalScopes()->where('user_id', $user->id)->latest('id')->limit(1)->update(['created_at' => $at]);
        }
    }

    private function read(string $uri, User $as): TestResponse
    {
        return $this->actingAs($as, 'web')->getJson('/api/v1/'.$uri);
    }

    public function test_a_user_reads_only_their_own_notifications(): void
    {
        $this->give($this->admin);
        $this->give($this->dataEntry);

        $rows = $this->read('notifications', $this->admin)->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Trip cancelled', $rows[0]['type_label']);
        $this->assertSame('Trip ALN-T0001 was cancelled.', $rows[0]['detail']);
        $this->assertFalse($rows[0]['read']);
    }

    public function test_the_list_is_newest_first_and_filters_by_type_and_range(): void
    {
        $this->give($this->admin, NotificationType::TRIP_CANCELLED, ['code' => 'ALN-T0001'], null, 'ALN-T0001', '2026-02-01 10:00:00');
        $this->give($this->admin, NotificationType::TRIP_CLIENT_APPROVED, ['code' => 'ALN-T0002'], null, 'ALN-T0002', '2026-02-10 10:00:00');
        $this->give($this->admin, NotificationType::TRIP_CREATED, ['code' => 'ALN-T0003'], null, 'ALN-T0003', '2026-02-20 10:00:00');

        $all = $this->read('notifications', $this->admin)->assertOk()->json('data');
        $this->assertSame(['ALN-T0003', 'ALN-T0002', 'ALN-T0001'], array_column($all, 'subject'));

        $this->assertSame(['ALN-T0002'], array_column($this->read('notifications?type=trip_client_approved', $this->admin)->json('data'), 'subject'));
        $this->assertSame(['ALN-T0002', 'ALN-T0001'], array_column($this->read('notifications?date_from=2026-02-01&date_to=2026-02-10', $this->admin)->json('data'), 'subject'));
    }

    public function test_the_search_matches_the_subject_and_the_type_label(): void
    {
        $this->give($this->admin, NotificationType::TRIP_CANCELLED, ['code' => 'ALN-T0001'], null, 'ALN-T0001');
        $this->give($this->admin, NotificationType::TRIP_CLIENT_APPROVED, ['code' => 'ALN-T0002'], null, 'ALN-T0002');

        $this->assertSame(['ALN-T0002'], array_column($this->read('notifications?search=T0002', $this->admin)->json('data'), 'subject'));
        $this->assertSame(['ALN-T0001'], array_column($this->read('notifications?search=cancelled', $this->admin)->json('data'), 'subject'));
    }

    public function test_the_bell_gives_the_latest_five_and_the_unread_count(): void
    {
        foreach (range(1, 7) as $i) {
            $this->give($this->admin, NotificationType::TRIP_CANCELLED, ['code' => "ALN-T000{$i}"], null, "ALN-T000{$i}");
        }

        $bell = $this->read('notifications/latest', $this->admin)->assertOk()->json();

        $this->assertCount(5, $bell['data']);
        $this->assertSame('ALN-T0007', $bell['data'][0]['subject']);
        $this->assertSame(7, $bell['unread_count']);
    }

    public function test_marking_as_read_touches_only_the_callers_rows(): void
    {
        $this->give($this->admin);
        $this->give($this->dataEntry);

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/notifications/read')->assertOk()->assertJsonPath('data.marked', 1);

        $this->assertSame(0, UserNotification::query()->withoutGlobalScopes()->where('user_id', $this->admin->id)->whereNull('read_at')->count());
        $this->assertSame(1, UserNotification::query()->withoutGlobalScopes()->where('user_id', $this->dataEntry->id)->whereNull('read_at')->count());
    }

    public function test_a_user_cannot_mark_someone_elses_notification_by_id(): void
    {
        $this->give($this->dataEntry);
        $theirs = UserNotification::query()->withoutGlobalScopes()->where('user_id', $this->dataEntry->id)->value('id');

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/notifications/read', ['ids' => [$theirs]])->assertOk()->assertJsonPath('data.marked', 0);

        $this->assertNull(UserNotification::query()->withoutGlobalScopes()->find($theirs)->read_at);
    }

    #[DataProvider('rolePages')]
    public function test_the_link_goes_to_the_page_of_the_readers_role(string $who, string $path): void
    {
        $trip = $this->createAwaiting();
        $user = match ($who) {
            'admin' => $this->admin,
            'client' => $this->clientManager,
            'driver' => $this->driver->user,
            'contractor' => User::factory()->contractorUser(Contractor::factory()->create())->create(),
        };

        $this->give($user, NotificationType::TRIP_CANCELLED, ['code' => $trip->code], $trip->id, $trip->code);

        $this->assertStringEndsWith($path.$trip->id, $this->read('notifications', $user)->json('data.0.url'));
    }

    public static function rolePages(): array
    {
        return [
            'staff' => ['admin', '/app/trip/view/'],
            'client' => ['client', '/app/client-trip/view/'],
            'driver' => ['driver', '/app/driver-trip/view/'],
            'contractor user' => ['contractor', '/app/contractor-trip/view/'],
        ];
    }

    public function test_a_contract_alert_links_to_the_contract_only_for_who_may_open_it(): void
    {
        Notifier::send($this->tenant->id, NotificationType::PO_EXHAUSTED, collect([$this->admin, $this->dataEntry]), ['po' => 'ALN-1200', 'service' => 'Solid Waste Transport'], purchaseOrderId: $this->po->id, subject: 'ALN-1200');

        $this->assertStringEndsWith("/app/po/view/{$this->po->id}", $this->read('notifications', $this->admin)->json('data.0.url'));
    }

    public function test_the_sentence_is_written_in_the_apps_language(): void
    {
        $this->give($this->admin);
        app()->setLocale('ar');

        $row = $this->read('notifications', $this->admin)->assertOk()->json('data.0');

        $this->assertNotSame('Trip cancelled', $row['type_label']);
        $this->assertStringContainsString('ALN-T0001', $row['detail']);
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/latest')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read')->assertUnauthorized();
    }
}
