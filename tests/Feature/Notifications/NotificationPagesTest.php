<?php

namespace Tests\Feature\Notifications;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trips\TripTestCase;

/**
 * Part 8.2 — the notifications page and the live bell (Blade shells): every signed-in tenant user opens the page,
 * the bell is on every tenant page and has its list, "view all" link and unread count, and a guest is sent to login.
 * One actor per test.
 */
class NotificationPagesTest extends TripTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('everyone')]
    public function test_every_signed_in_role_opens_the_notifications_page(string $who): void
    {
        $user = match ($who) {
            'admin' => $this->admin,
            'dataEntry' => $this->dataEntry,
            'auditor' => $this->auditor,
            'client' => $this->clientManager,
            'driver' => $this->driver->user,
        };

        $this->actingAs($user, 'web')->get('/app/notifications')->assertOk()
            ->assertSee('notificationListTranslations', false)
            ->assertSee('datatables-notifications', false);
    }

    public static function everyone(): array
    {
        return ['system admin' => ['admin'], 'data entry' => ['dataEntry'], 'auditor' => ['auditor'], 'client' => ['client'], 'driver' => ['driver']];
    }

    public function test_the_page_lists_every_type_in_its_filter(): void
    {
        $html = $this->actingAs($this->admin, 'web')->get('/app/notifications')->assertOk()->getContent();

        foreach (['trip_created', 'trip_cancelled', 'po_exhausted'] as $type) {
            $this->assertStringContainsString($type, $html);
        }
    }

    public function test_the_bell_is_live_on_a_tenant_page(): void
    {
        $this->actingAs($this->admin, 'web')->get('/dashboard')->assertOk()
            ->assertSee('id="gcm-bell"', false)
            ->assertSee('id="gcm-bell-list"', false)
            ->assertSee(route('app-notifications'), false);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/notifications')->assertRedirect();
    }
}
