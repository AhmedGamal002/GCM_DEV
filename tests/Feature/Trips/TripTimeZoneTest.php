<?php

namespace Tests\Feature\Trips;

use App\Models\Trip;

/**
 * The trip's date-time is an INSTANT: the browser form sends the user's wall clock with its offset
 * ("2026-03-01T12:30:00+03:00"), and the server must store that same instant in the app's zone —
 * not the sender's wall clock as if it were the app's (Eloquent writes a Carbon in its own zone).
 * A value with no offset keeps meaning "the app's zone", as before.
 */
class TripTimeZoneTest extends TripTestCase
{
    private function create(string $tripAt): Trip
    {
        $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/trips', $this->staffData([
            'trip_at' => $tripAt,
            'quantity_ton' => '3.75',
        ]));

        $response->assertCreated();

        return Trip::findOrFail($response->json('data.id'));
    }

    public function test_a_time_with_an_offset_is_stored_as_the_same_instant(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        $trip = $this->create('2026-03-01T12:30:00+03:00');

        $this->assertSame('2026-03-01T09:30:00+00:00', $trip->trip_at->setTimezone('UTC')->toIso8601String());
        $this->assertSame('2026-03-01 09:30:00', $trip->getRawOriginal('trip_at'));
    }

    public function test_a_time_without_an_offset_means_the_apps_zone(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        $trip = $this->create('2026-03-01 09:30');

        $this->assertSame('2026-03-01 09:30:00', $trip->getRawOriginal('trip_at'));
    }

    public function test_the_same_instant_in_two_zones_is_stored_once(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        $a = $this->create('2026-03-01T09:30:00+00:00');
        $b = $this->create('2026-03-01T11:30:00+02:00');

        $this->assertSame($a->getRawOriginal('trip_at'), $b->getRawOriginal('trip_at'));
    }
}
