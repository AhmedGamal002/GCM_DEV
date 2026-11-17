<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Events\PurchaseOrderLineStatusChanged;
use App\Domain\PurchaseOrders\Exceptions\LedgerIsAppendOnlyException;
use App\Domain\PurchaseOrders\Exceptions\QuotaAlreadyConsumedException;
use App\Domain\PurchaseOrders\Quota\DeductQuotaAction;
use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * The quota ledger (part 6.1): append-only, deduct / release, idempotence,
 * negative balances (ب6) and the status events for 8.2.
 */
class QuotaLedgerTest extends PurchaseOrderTestCase
{
    // ------------------------------------------------------------ deduct

    public function test_deducting_a_trip_writes_one_consume_row_and_the_balance_follows(): void
    {
        $line = $this->line(['quota_method' => 'trips_and_ancillary', 'max_trips' => 150, 'trip_cost' => 50, 'ancillary_cost_per_trip' => 30]);

        $tripId = $this->newTrip($line);
        $entry = app(DeductQuotaAction::class)->execute($line, $tripId, 50, 30, $this->admin);

        $this->assertSame(QuotaLedgerEntry::TYPE_CONSUME, $entry->type);
        $this->assertSame($tripId, $entry->trip_id);
        $this->assertSame(1, $entry->trips_delta);
        $this->assertSame('50.00', $entry->trip_cost_delta);
        $this->assertSame('30.00', $entry->ancillary_cost_delta);
        $this->assertSame($this->admin->id, $entry->created_by);
        $this->assertSame($this->tenant->id, $entry->tenant_id);

        $balance = QuotaBalances::for($line);
        $this->assertSame(1, $balance->tripsUsed);
        $this->assertSame(149, $balance->tripsRemaining());
        $this->assertSame(7450.0, $balance->tripCostRemaining());
        $this->assertSame(4470.0, $balance->ancillaryRemaining());
    }

    public function test_a_deduct_past_the_limits_goes_through_and_the_balance_turns_negative(): void
    {
        // ب6: a trip is recorded after it happened — never refused for lack of balance.
        $line = $this->line(['max_trips' => 2, 'trip_cost' => 50]);
        $this->charge($line, 2);

        $this->assertFalse(QuotaBalances::for($line)->canCover(50));

        $this->charge($line, 1);

        $balance = QuotaBalances::for($line);
        $this->assertSame(-1, $balance->tripsRemaining());
        $this->assertSame(-50.0, $balance->tripCostRemaining());
        $this->assertTrue($balance->isExhausted());
    }

    public function test_cover_checks_trips_trip_cost_and_ancillary_cost(): void
    {
        $line = $this->line(['quota_method' => 'trips_and_ancillary', 'max_trips' => 3, 'trip_cost' => 10, 'ancillary_cost_per_trip' => 5]);
        $this->charge($line, 1, 10, 5);

        $balance = QuotaBalances::for($line);
        $this->assertTrue($balance->canCover(10, 5));
        $this->assertTrue($balance->canCover(20, 10));   // exactly what is left (20 / 10)
        $this->assertFalse($balance->canCover(20.01, 5)); // trip cost total
        $this->assertFalse($balance->canCover(10, 10.01)); // ancillary total
    }

    public function test_a_trips_only_line_refuses_an_ancillary_cost_and_costs_cannot_be_negative(): void
    {
        $line = $this->line(['quota_method' => 'trips_only']);

        try {
            app(DeductQuotaAction::class)->execute($line, $this->newTrip($line), 50, 10, $this->admin);
            $this->fail('An ancillary cost on a trips-only line must be refused.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        app(DeductQuotaAction::class)->execute($line, $this->newTrip($line), -1, 0, $this->admin);
    }

    public function test_a_trip_cannot_be_deducted_twice_without_a_release(): void
    {
        $line = $this->line();
        $other = $this->line(['main_service_id' => $this->service->id], '2026-06-01', '2026-12-31');

        $tripId = $this->newTrip($line);
        app(DeductQuotaAction::class)->execute($line, $tripId, 50, 0, $this->admin);

        $this->expectException(QuotaAlreadyConsumedException::class);
        app(DeductQuotaAction::class)->execute($other, $tripId, 50, 0, $this->admin);
    }

    // ------------------------------------------------------------ release

    public function test_release_reverses_every_open_consume_of_the_trip_and_is_idempotent(): void
    {
        $line = $this->line();
        [$trip] = $this->charge($line, 1, 50);
        $this->charge($line, 1, 50); // another trip, untouched

        $this->assertSame(1, app(ReleaseQuotaAction::class)->execute($trip, $this->admin));
        $this->assertSame(0, app(ReleaseQuotaAction::class)->execute($trip, $this->admin));

        $release = QuotaLedgerEntry::where('trip_id', $trip)->where('type', 'release')->sole();
        $consume = QuotaLedgerEntry::where('trip_id', $trip)->where('type', 'consume')->sole();
        $this->assertSame($consume->id, $release->reverses_id);
        $this->assertSame(-1, $release->trips_delta);
        $this->assertSame('-50.00', $release->trip_cost_delta);

        $balance = QuotaBalances::for($line);
        $this->assertSame(1, $balance->tripsUsed);
        $this->assertSame(50.0, $balance->tripCostUsed);
    }

    public function test_releasing_a_trip_that_consumed_nothing_writes_nothing(): void
    {
        $this->assertSame(0, app(ReleaseQuotaAction::class)->execute(999, $this->admin));
        $this->assertSame(0, QuotaLedgerEntry::count());
    }

    public function test_a_consume_can_only_ever_be_reversed_once_even_bypassing_the_action(): void
    {
        $line = $this->line();
        [$trip] = $this->charge($line);
        app(ReleaseQuotaAction::class)->execute($trip, $this->admin);
        $consume = QuotaLedgerEntry::where('type', 'consume')->sole();

        $this->expectException(UniqueConstraintViolationException::class);
        QuotaLedgerEntry::create([
            'purchase_order_service_id' => $line->id, 'trip_id' => $trip, 'type' => 'release',
            'trips_delta' => -1, 'trip_cost_delta' => -50, 'ancillary_cost_delta' => 0,
            'reverses_id' => $consume->id, 'occurred_at' => now(), 'created_by' => $this->admin->id,
        ]);
    }

    public function test_moving_a_trip_to_another_line_is_release_then_deduct_and_keeps_the_history(): void
    {
        // ب7: a trip whose date / project / service changed is re-linked.
        $old = $this->line();
        $new = $this->line([], '2026-06-01', '2026-12-31');
        [$trip] = $this->charge($old, 1, 50);

        app(ReleaseQuotaAction::class)->execute($trip, $this->admin);
        app(DeductQuotaAction::class)->execute($new, $trip, 70, 0, $this->admin);

        $this->assertSame(0, QuotaBalances::for($old)->tripsUsed);
        $this->assertSame(1, QuotaBalances::for($new)->tripsUsed);
        $this->assertSame(70.0, QuotaBalances::for($new)->tripCostUsed);
        $this->assertSame(3, QuotaLedgerEntry::where('trip_id', $trip)->count());
        $this->assertSame(1, QuotaLedgerEntry::openConsumptionsOf($trip)->count());
    }

    public function test_a_balance_read_as_of_a_past_day_ignores_the_rows_written_after_it(): void
    {
        $line = $this->line();
        $this->charge($line, 2);

        $this->travelTo(now()->addDays(3));
        $this->charge($line, 1);

        $this->assertSame(3, QuotaBalances::for($line)->tripsUsed);
        $this->assertSame(2, QuotaBalances::for($line, now()->subDays(2))->tripsUsed);
    }

    // ------------------------------------------------------------ append-only

    public function test_a_ledger_row_cannot_be_edited(): void
    {
        $this->charge($line = $this->line());
        $entry = QuotaLedgerEntry::first();

        $this->expectException(LedgerIsAppendOnlyException::class);
        $entry->trips_delta = 5;
        $entry->save();
    }

    public function test_a_ledger_row_cannot_be_deleted(): void
    {
        $this->charge($this->line());

        $this->expectException(LedgerIsAppendOnlyException::class);
        QuotaLedgerEntry::first()->delete();
    }

    public function test_the_ledger_refuses_mass_updates_and_deletes(): void
    {
        $this->charge($this->line(), 2);

        $first = QuotaLedgerEntry::query()->orderBy('id')->first();

        foreach ([
            'update' => fn () => QuotaLedgerEntry::query()->update(['trips_delta' => 0]),
            'upsert' => fn () => QuotaLedgerEntry::query()->upsert([['id' => $first->id, 'trips_delta' => 0]], ['id'], ['trips_delta']),
            'touch' => fn () => QuotaLedgerEntry::query()->touch('created_at'),
            'increment' => fn () => QuotaLedgerEntry::query()->increment('trips_delta'),
            'decrement' => fn () => QuotaLedgerEntry::query()->decrement('trips_delta'),
            'incrementEach' => fn () => QuotaLedgerEntry::query()->incrementEach(['trips_delta' => 1]),
            'decrementEach' => fn () => QuotaLedgerEntry::query()->decrementEach(['trips_delta' => 1]),
            'updateOrInsert' => fn () => QuotaLedgerEntry::query()->updateOrInsert(['id' => $first->id], ['trips_delta' => 0]),
            'updateFrom' => fn () => QuotaLedgerEntry::query()->updateFrom(['trips_delta' => 0]),
            'delete' => fn () => QuotaLedgerEntry::query()->delete(),
            'forceDelete' => fn () => QuotaLedgerEntry::query()->forceDelete(),
            'truncate' => fn () => QuotaLedgerEntry::query()->truncate(),
            'static truncate' => fn () => QuotaLedgerEntry::truncate(),
            'saveQuietly' => fn () => tap(QuotaLedgerEntry::find($first->id), fn ($e) => $e->trips_delta = 9)->saveQuietly(),
            'deleteQuietly' => fn () => QuotaLedgerEntry::find($first->id)->deleteQuietly(),
        ] as $name => $write) {
            try {
                $write();
                $this->fail("The ledger accepted a write through {$name}().");
            } catch (LedgerIsAppendOnlyException) {
            }
        }

        $this->assertSame(2, QuotaLedgerEntry::count());
        $this->assertSame(2, (int) QuotaLedgerEntry::sum('trips_delta'));
    }

    // ------------------------------------------------------------ concurrency

    public function test_a_failed_trip_write_leaves_no_ledger_row_behind(): void
    {
        // Deduct runs inside the caller's transaction: if the trip write fails after it, nothing stays.
        $line = $this->line();

        try {
            $tripId = $this->newTrip($line);
            DB::transaction(function () use ($line, $tripId) {
                app(DeductQuotaAction::class)->execute($line, $tripId, 50, 0, $this->admin);
                throw new RuntimeException('the trip insert failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, QuotaLedgerEntry::count());
        $this->assertSame(0, QuotaBalances::for($line)->tripsUsed);
    }

    // ------------------------------------------------------------ status events (for 8.2)

    public function test_crossing_the_alert_line_and_running_out_fire_one_event_each(): void
    {
        $line = $this->line(['max_trips' => 5, 'trip_cost' => 10, 'alert_percent' => 40]);
        $seen = [];
        Event::listen(PurchaseOrderLineStatusChanged::class, function (PurchaseOrderLineStatusChanged $e) use (&$seen) {
            $seen[] = [$e->line->id, $e->from, $e->to];
        });

        // 5 trips, alert 40% → near once fewer than 2 are left (after the 4th trip), exhausted at the 5th.
        $this->charge($line, 5, 10);

        $this->assertSame([
            [$line->id, PurchaseOrderStatus::VALID, PurchaseOrderStatus::NEAR_EXHAUSTION],
            [$line->id, PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::EXHAUSTED],
        ], $seen);
    }

    public function test_a_release_that_brings_the_line_back_fires_the_reverse_event(): void
    {
        $line = $this->line(['max_trips' => 1, 'trip_cost' => 10]);
        [$trip] = $this->charge($line, 1, 10);

        Event::fake([PurchaseOrderLineStatusChanged::class]);
        app(ReleaseQuotaAction::class)->execute($trip, $this->admin);

        Event::assertDispatched(PurchaseOrderLineStatusChanged::class, fn ($e) => $e->line->is($line)
            && $e->from === PurchaseOrderStatus::EXHAUSTED
            && $e->to === PurchaseOrderStatus::VALID);
    }

    public function test_no_event_fires_when_the_status_stays_the_same_or_the_write_rolls_back(): void
    {
        $line = $this->line(['max_trips' => 100]);
        $count = 0;
        Event::listen(PurchaseOrderLineStatusChanged::class, function () use (&$count) {
            $count++;
        });

        $this->charge($line, 1);
        $this->assertSame(0, $count);

        $small = $this->line(['max_trips' => 1], '2026-06-01', '2026-12-31');

        $tripId = $this->newTrip($small);

        try {
            DB::transaction(function () use ($small, $tripId) {
                app(DeductQuotaAction::class)->execute($small, $tripId, 50, 0, $this->admin);
                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $count, 'An event of a rolled-back write must not reach the listeners.');
        $this->assertInstanceOf(PurchaseOrderService::class, $small);
    }
}
