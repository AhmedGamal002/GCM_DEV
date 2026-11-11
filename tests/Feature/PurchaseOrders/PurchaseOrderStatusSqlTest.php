<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ReleaseQuotaAction;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStatusSql;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\Tenant;

/**
 * PurchaseOrderStatusSql is the database twin of PurchaseOrderStatus: the list
 * filters and sorts by "الحالة العامة للبنود" in SQL. This test is what keeps the
 * two from drifting — it builds a PO for every edge of every rule (each
 * component right at, just under and just over its alert line; one line against
 * several; a released trip; the PO's dates before / during / after), then asks
 * the PHP rule and the SQL for the status of every line and every PO and
 * requires the same answer. Like DocumentsStatusColumnTest for the documents.
 *
 * "Today" is 2026-03-01 (the base class freezes it); the default PO runs
 * 2026-01-01 → 2026-05-30 (150 days).
 */
class PurchaseOrderStatusSqlTest extends PurchaseOrderTestCase
{
    /** @var array<string, PurchaseOrder> scenario name → its PO */
    private array $orders = [];

    /** A PO of its own (own project, so POs never share a service) with the given lines; returns the line models. */
    private function po(string $name, array $lines, string $startsOn = '2026-01-01', string $endsOn = '2026-05-30'): array
    {
        $project = Project::factory()->for($this->company)->create(['name' => $name]);
        $order = PurchaseOrder::factory()->forProject($project)->between($startsOn, $endsOn)->create();
        $this->orders[$name] = $order;

        $made = [];
        foreach ($lines as $line) {
            $made[] = PurchaseOrderService::factory()->create([
                'purchase_order_id' => $order->id,
                'main_service_id' => MainService::factory()->create()->id,
                ...$line,
            ]);
        }

        return $made;
    }

    /** Every scenario: the DB rows are built here, the checks compare PHP with SQL. */
    private function build(): void
    {
        // ── trips: 10 max, alert 20% → near only when fewer than 2 are left (strictly under) ──
        foreach ([0, 7, 8, 9, 10, 11] as $used) {
            [$line] = $this->po("trips used {$used}", [['max_trips' => 10]]);
            $this->charge($line, $used, 10);
        }

        // ── trip cost: 1000 total (10 × 100); a few trips at a high cost, so the trips themselves stay fine ──
        foreach ([['at 20%', 4, 200], ['under 20%', 5, 170], ['used up', 5, 200], ['over', 6, 200], ['just under', 4, 200.01]] as [$name, $count, $cost]) {
            [$line] = $this->po("cost {$name}", [['max_trips' => 10, 'trip_cost' => 100]]);
            $this->charge($line, $count, $cost);
        }

        // ── ancillary: counted only for the method that tracks it ──
        foreach ([['ok', 3, 40], ['near', 3, 85], ['at 20%', 3, 80], ['used up', 3, 100]] as [$name, $count, $anc]) {
            [$line] = $this->po("ancillary {$name}", [['max_trips' => 10, 'trip_cost' => 100, 'quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 30, 'alert_percent' => 20]]);
            $this->charge($line, $count, 10, $anc);
        }

        // ── money that doesn't add up in binary floating point ──
        [$line] = $this->po('cents used up', [['max_trips' => 30, 'trip_cost' => 33.33]]);
        $this->charge($line, 3, 333.30);               // 999.90 = exactly the total (30 × 33.33)
        [$line] = $this->po('cents one halala short', [['max_trips' => 30, 'trip_cost' => 33.33]]);
        $this->charge($line, 3, 333.29);
        [$line] = $this->po('tenths', [['max_trips' => 3, 'trip_cost' => 0.1, 'alert_percent' => 34]]);
        $this->charge($line, 2, 0.1);                  // 0.2 of 0.3

        // ── a different alert percentage per line, and the extremes ──
        [$line] = $this->po('alert 1', [['max_trips' => 100, 'alert_percent' => 1]]);
        $this->charge($line, 99, 1);
        [$line] = $this->po('alert 99', [['max_trips' => 100, 'alert_percent' => 99]]);
        $this->charge($line, 2, 1);

        // ── the PO's dates (150 days: Jan 1 → May 30; today = Mar 1) ──
        $this->po('running', [[]]);
        $this->po('not started', [[]], '2026-03-02', '2026-04-01');
        $this->po('started today', [[]], '2026-03-01', '2026-05-30');
        $this->po('ends today', [[]], '2026-01-01', '2026-03-01');
        $this->po('ended yesterday', [[]], '2026-01-01', '2026-02-28');
        $this->po('ended long ago', [[]], '2024-01-01', '2024-06-30');
        $this->po('one day only, today', [[]], '2026-03-01', '2026-03-01');
        // 100 days, 20 left (today + 19) → exactly 20%: not near; 19 left → near
        $this->po('days at 20%', [[]], '2025-12-11', '2026-03-20');
        $this->po('days under 20%', [[]], '2025-12-10', '2026-03-19');
        // a PO that hasn't started has its date ok however short it is
        $this->po('not started and short', [[]], '2026-03-05', '2026-03-06');

        // ── several lines ──
        [$ok, $gone] = $this->po('lines valid + exhausted', [['max_trips' => 10], ['max_trips' => 10]]);
        $this->charge($gone, 10, 10);
        [$a, $b] = $this->po('lines exhausted + exhausted', [['max_trips' => 10], ['max_trips' => 10]]);
        $this->charge($a, 10, 10);
        $this->charge($b, 10, 10);
        [$near, $ok] = $this->po('lines near + valid', [['max_trips' => 10], ['max_trips' => 10]]);
        $this->charge($near, 9, 10);
        [$gone, $near] = $this->po('lines exhausted + near', [['max_trips' => 10], ['max_trips' => 10]]);
        $this->charge($gone, 10, 10);
        $this->charge($near, 9, 10);
        $this->po('lines valid + valid', [[], []]);
        [$gone, $ok, $near] = $this->po('three lines mixed', [['max_trips' => 10], [], ['max_trips' => 10]]);
        $this->charge($gone, 10, 10);
        $this->charge($near, 9, 10);

        // ── a released (cancelled) trip frees its quota again ──
        [$line] = $this->po('released', [['max_trips' => 10]]);
        $ids = $this->charge($line, 10, 10);
        foreach (array_slice($ids, 0, 1) as $tripId) {
            app(ReleaseQuotaAction::class)->execute($tripId, $this->admin);
        }
        [$line] = $this->po('released all', [['max_trips' => 10]]);
        foreach ($this->charge($line, 10, 10) as $tripId) {
            app(ReleaseQuotaAction::class)->execute($tripId, $this->admin);
        }

        // ── over the limit (a trip recorded after the fact goes negative) ──
        [$line] = $this->po('negative', [['max_trips' => 5, 'trip_cost' => 10]]);
        $this->charge($line, 8, 999);

        // ── a PO with no line at all is valid ──
        $project = Project::factory()->for($this->company)->create();
        $this->orders['no lines'] = PurchaseOrder::factory()->forProject($project)->create();
    }

    public function test_every_line_gets_the_same_status_in_sql_and_in_php(): void
    {
        $this->build();

        $sql = PurchaseOrderStatusSql::lines()->pluck('line_status', 'line_id');

        $rank = [
            PurchaseOrderStatus::VALID => PurchaseOrderStatusSql::VALID,
            PurchaseOrderStatus::NEAR_EXHAUSTION => PurchaseOrderStatusSql::NEAR,
            PurchaseOrderStatus::EXHAUSTED => PurchaseOrderStatusSql::EXHAUSTED,
        ];

        $lines = PurchaseOrderService::with('purchaseOrder')->get();
        $this->assertGreaterThan(40, $lines->count());
        $this->assertCount($lines->count(), $sql, 'one SQL row per line');

        foreach ($lines as $line) {
            $php = PurchaseOrderStatus::line(QuotaBalances::for($line));

            $this->assertSame($rank[$php], (int) $sql[$line->id], "line {$line->id} of [{$line->purchaseOrder->project->name}]: PHP says {$php}");
        }
    }

    public function test_every_po_is_in_exactly_the_status_filter_php_gives_it(): void
    {
        $this->build();

        $byFilter = [];
        foreach (PurchaseOrderStatus::STATUSES as $status) {
            $byFilter[$status] = PurchaseOrderStatusSql::filter(PurchaseOrder::query(), $status)->pluck('purchase_orders.id')->map(fn ($id) => (int) $id)->all();
        }

        // the three filters partition every PO
        $this->assertEmpty(array_intersect($byFilter['valid'], $byFilter['near_exhaustion']));
        $this->assertEmpty(array_intersect($byFilter['valid'], $byFilter['exhausted']));
        $this->assertEmpty(array_intersect($byFilter['near_exhaustion'], $byFilter['exhausted']));
        $this->assertCount(PurchaseOrder::count(), array_merge(...array_values($byFilter)));

        $pos = PurchaseOrder::with('services')->get();
        $this->assertGreaterThanOrEqual(35, $pos->count());

        foreach ($pos as $po) {
            $name = array_search($po->id, array_map(fn ($o) => $o->id, $this->orders), true);
            $expected = PurchaseOrderStatus::order(QuotaBalances::forMany($po->services));

            $this->assertContains($po->id, $byFilter[$expected], "[{$name}] PHP says {$expected}, SQL put the PO in another filter");
        }
    }

    public function test_the_expected_status_of_each_hand_checked_scenario(): void
    {
        $this->build();

        $statusOf = function (string $name): string {
            foreach (PurchaseOrderStatus::STATUSES as $status) {
                if (PurchaseOrderStatusSql::filter(PurchaseOrder::query(), $status)->whereKey($this->orders[$name]->id)->exists()) {
                    return $status;
                }
            }

            return 'none';
        };

        $expected = [
            // trips: strictly under the alert line is near, using the last one is exhausted
            'trips used 0' => 'valid', 'trips used 7' => 'valid', 'trips used 8' => 'valid',
            'trips used 9' => 'near_exhaustion', 'trips used 10' => 'exhausted', 'trips used 11' => 'exhausted',
            // cost
            'cost at 20%' => 'valid', 'cost under 20%' => 'near_exhaustion', 'cost used up' => 'exhausted', 'cost over' => 'exhausted',
            'cost just under' => 'near_exhaustion',
            // ancillary (a trips-only line can't be charged one — DeductQuotaAction refuses it)
            'ancillary ok' => 'valid', 'ancillary near' => 'near_exhaustion', 'ancillary at 20%' => 'valid', 'ancillary used up' => 'exhausted',
            // money in halalas
            'cents used up' => 'exhausted', 'cents one halala short' => 'near_exhaustion', 'tenths' => 'near_exhaustion',
            'alert 1' => 'valid', 'alert 99' => 'near_exhaustion',
            // dates
            'running' => 'valid', 'not started' => 'valid', 'started today' => 'valid', 'ends today' => 'near_exhaustion',
            'ended yesterday' => 'exhausted', 'ended long ago' => 'exhausted', 'one day only, today' => 'valid',
            'days at 20%' => 'valid', 'days under 20%' => 'near_exhaustion', 'not started and short' => 'valid',
            // several lines
            'lines valid + exhausted' => 'near_exhaustion', 'lines exhausted + exhausted' => 'exhausted',
            'lines near + valid' => 'near_exhaustion', 'lines exhausted + near' => 'near_exhaustion',
            'lines valid + valid' => 'valid', 'three lines mixed' => 'near_exhaustion',
            // ledger
            'released' => 'near_exhaustion', 'released all' => 'valid', 'negative' => 'exhausted', 'no lines' => 'valid',
        ];

        foreach ($expected as $name => $status) {
            $this->assertSame($status, $statusOf($name), "[{$name}]");
        }
    }

    public function test_sorting_by_status_puts_valid_first_ascending_and_exhausted_first_descending(): void
    {
        $this->build();

        $rank = fn (PurchaseOrder $po) => array_search(
            PurchaseOrderStatus::order(QuotaBalances::forMany($po->load('services')->services)),
            PurchaseOrderStatus::STATUSES,
            true,
        );

        $asc = PurchaseOrderStatusSql::orderBy(PurchaseOrder::query(), 'asc')->get();
        $ranks = $asc->map($rank)->all();
        $sorted = $ranks;
        sort($sorted);
        $this->assertSame($sorted, $ranks, 'ascending: valid, then about to run out, then exhausted');
        $this->assertCount(PurchaseOrder::count(), $asc, 'the join does not duplicate or drop a PO');

        $desc = PurchaseOrderStatusSql::orderBy(PurchaseOrder::query(), 'desc')->get()->map($rank)->all();
        $reversed = $desc;
        rsort($reversed);
        $this->assertSame($reversed, $desc);
    }

    public function test_the_filter_never_looks_at_another_tenants_ledger(): void
    {
        $this->build();
        $before = PurchaseOrderStatusSql::filter(PurchaseOrder::query(), 'exhausted')->count();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $line = $this->theirLine();
        $this->charge($line, 10, 10);
        $this->assertSame(1, PurchaseOrderStatusSql::filter(PurchaseOrder::query(), 'exhausted')->count());

        app()->instance('tenant', $this->tenant);
        $this->assertSame($before, PurchaseOrderStatusSql::filter(PurchaseOrder::query(), 'exhausted')->count());
    }

    /** A 10-trip line in the OTHER tenant (the current one when called). */
    private function theirLine(): PurchaseOrderService
    {
        $company = \App\Models\Company::factory()->create(['prefix' => 'ZZZ']);
        $project = Project::factory()->for($company)->create();
        $order = PurchaseOrder::factory()->forProject($project)->between('2026-01-01', '2026-05-30')->create();

        return PurchaseOrderService::factory()->create([
            'purchase_order_id' => $order->id,
            'main_service_id' => MainService::factory()->create()->id,
            'max_trips' => 10,
        ]);
    }

    public function test_an_unknown_status_leaves_the_query_alone(): void
    {
        $this->build();

        $this->assertSame(PurchaseOrder::count(), PurchaseOrderStatusSql::filter(PurchaseOrder::query(), 'bogus')->count());
    }
}
