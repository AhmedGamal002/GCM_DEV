<?php

namespace Tests\Feature\PurchaseOrders;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Quota\QuotaBalance;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\PurchaseOrders\Quota\ServiceExhaustion;
use App\Models\MainService;
use App\Models\PurchaseOrderService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Decisions ب8 / ب9: each component of a line against the LINE'S alert
 * percentage (strictly under it), the line's status, the PO's items status
 * and its timeline. "Today" is 2026-03-01 (PurchaseOrderTestCase).
 */
class PurchaseOrderStatusTest extends PurchaseOrderTestCase
{
    public function test_a_fresh_running_line_is_in_force_with_every_component_ok(): void
    {
        $line = $this->line();
        $components = PurchaseOrderStatus::components(QuotaBalances::for($line));

        $this->assertSame(PurchaseOrderStatus::VALID, PurchaseOrderStatus::line(QuotaBalances::for($line)));
        $this->assertSame(['date', 'trips', 'trip_cost'], array_keys($components));
        $this->assertSame(['ok'], array_values(array_unique(array_column($components, 'state'))));
        $this->assertSame(100, $components['trips']['percent_remaining']);
    }

    /** @return array<string, array{int, string}> trips used of 100 at a 20% alert → line status */
    public static function tripsAroundTheAlertLine(): array
    {
        return [
            '79 used, 21% left' => [79, PurchaseOrderStatus::VALID],
            '80 used, exactly 20% left' => [80, PurchaseOrderStatus::VALID],
            '81 used, 19% left' => [81, PurchaseOrderStatus::NEAR_EXHAUSTION],
            '99 used, 1 left' => [99, PurchaseOrderStatus::NEAR_EXHAUSTION],
            '100 used, none left' => [100, PurchaseOrderStatus::EXHAUSTED],
            '101 used, below zero' => [101, PurchaseOrderStatus::EXHAUSTED],
        ];
    }

    /** @dataProvider tripsAroundTheAlertLine */
    public function test_the_trips_component_against_the_alert_percentage(int $used, string $expected): void
    {
        $line = $this->line(['max_trips' => 100, 'trip_cost' => 50, 'alert_percent' => 20]);
        $this->charge($line, $used, 50);

        $this->assertSame($expected, PurchaseOrderStatus::line(QuotaBalances::for($line)));
    }

    public function test_the_alert_percentage_is_the_lines_own(): void
    {
        $tight = $this->line(['max_trips' => 10, 'alert_percent' => 50]);
        $loose = $this->line(['max_trips' => 10, 'alert_percent' => 10], '2026-06-01', '2026-12-31');
        $this->charge($tight, 6);
        $this->charge($loose, 6);

        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::line(QuotaBalances::for($tight)));
        $this->assertSame(PurchaseOrderStatus::VALID, PurchaseOrderStatus::line(QuotaBalances::for($loose)));
    }

    public function test_the_trip_cost_total_is_watched_on_its_own(): void
    {
        // The trip cost went up after the PO was signed (ب3): 100 trips at 100 SAR =
        // 10,000 max, but trips charged at 150 → the money runs out before the trips.
        $line = $this->line(['max_trips' => 100, 'trip_cost' => 100, 'alert_percent' => 20]);
        $this->charge($line, 55, 150); // 8,250 of 10,000 spent → 17.5% left, trips 45% left

        $components = PurchaseOrderStatus::components(QuotaBalances::for($line));
        $this->assertSame('ok', $components['trips']['state']);
        $this->assertSame('near', $components['trip_cost']['state']);
        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::line(QuotaBalances::for($line)));

        $this->charge($line, 12, 150); // 10,050 → over the max
        $this->assertSame([ServiceExhaustion::TRIP_COST], ServiceExhaustion::exhaustedComponents(QuotaBalances::for($line)));
        $this->assertSame(PurchaseOrderStatus::EXHAUSTED, PurchaseOrderStatus::line(QuotaBalances::for($line)));
    }

    public function test_the_ancillary_cost_total_is_watched_on_its_own_too(): void
    {
        $line = $this->line(['quota_method' => 'trips_and_ancillary', 'max_trips' => 10, 'trip_cost' => 50, 'ancillary_cost_per_trip' => 30, 'alert_percent' => 20]);
        $this->charge($line, 3, 50, 85); // ancillary 255 of 300 → 15% left; trips and trip cost 70% left

        $components = PurchaseOrderStatus::components(QuotaBalances::for($line));
        $this->assertSame('ok', $components['trips']['state']);
        $this->assertSame('ok', $components['trip_cost']['state']);
        $this->assertSame('near', $components['ancillary_cost']['state']);
        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::line(QuotaBalances::for($line)));
    }

    public function test_a_trips_only_line_has_no_ancillary_component(): void
    {
        $line = $this->line(['quota_method' => 'trips_only', 'ancillary_cost_per_trip' => 0]);

        $this->assertArrayNotHasKey('ancillary_cost', PurchaseOrderStatus::components(QuotaBalances::for($line)));
        $this->assertSame(0.0, $line->maxAncillaryCost());
    }

    public function test_the_date_component_counts_the_days_left_out_of_the_whole_duration(): void
    {
        // 1–10 March = 10 days; alert 20% → near once fewer than 2 days are left (the last day).
        $line = $this->line(['alert_percent' => 20], '2026-03-01', '2026-03-10');

        $this->travelTo(now()->setDate(2026, 3, 9));
        $balance = QuotaBalances::for($line);
        $this->assertSame(10, $balance->totalDays);
        $this->assertSame(2, $balance->daysRemaining);
        $this->assertSame('ok', PurchaseOrderStatus::components($balance)['date']['state']);

        $this->travelTo(now()->setDate(2026, 3, 10));
        $balance = QuotaBalances::for($line);
        $this->assertSame(1, $balance->daysRemaining);
        $this->assertSame('near', PurchaseOrderStatus::components($balance)['date']['state']);
        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::line($balance));
    }

    public function test_a_po_that_has_not_started_counts_the_days_until_it_does(): void
    {
        $line = $this->line([], '2026-03-11', '2026-04-09');
        $balance = QuotaBalances::for($line);

        $this->assertSame(QuotaBalance::TIMELINE_NOT_STARTED, $balance->timeline);
        $this->assertSame(10, $balance->daysUntilStart);
        $this->assertSame(30, $balance->daysRemaining);
        $this->assertSame('ok', PurchaseOrderStatus::components($balance)['date']['state']);
        $this->assertSame(PurchaseOrderStatus::VALID, PurchaseOrderStatus::line($balance));
    }

    public function test_a_po_whose_dates_ended_is_exhausted_and_says_since_when(): void
    {
        $line = $this->line([], '2026-01-01', '2026-02-26');
        $this->charge($line, 1);
        $balance = QuotaBalances::for($line);

        $this->assertSame(QuotaBalance::TIMELINE_ENDED, $balance->timeline);
        $this->assertSame(3, $balance->daysSinceEnd);
        $this->assertSame(0, $balance->daysRemaining);
        $this->assertSame([ServiceExhaustion::DATE], ServiceExhaustion::exhaustedComponents($balance));
        $this->assertSame(PurchaseOrderStatus::EXHAUSTED, PurchaseOrderStatus::order([$balance]));
    }

    public function test_one_exhausted_line_makes_the_po_about_to_run_out_and_all_of_them_fully_exhausted(): void
    {
        // ب8: "fully exhausted" = every service ran out; one alone = "about to run out".
        $a = $this->line(['max_trips' => 2]);
        $b = PurchaseOrderService::factory()->create([
            'purchase_order_id' => $a->purchase_order_id,
            'main_service_id' => MainService::factory()->create()->id,
            'max_trips' => 2,
        ]);
        $po = $a->purchaseOrder;

        $this->assertSame(PurchaseOrderStatus::VALID, PurchaseOrderStatus::describe($po)['status']);

        $this->charge($a, 2);
        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::describe($po->fresh())['status']);

        $this->charge($b, 2);
        $this->assertSame(PurchaseOrderStatus::EXHAUSTED, PurchaseOrderStatus::describe($po->fresh())['status']);
    }

    public function test_a_line_near_its_alert_makes_the_po_about_to_run_out(): void
    {
        $a = $this->line(['max_trips' => 10, 'alert_percent' => 20]);
        PurchaseOrderService::factory()->create(['purchase_order_id' => $a->purchase_order_id, 'main_service_id' => MainService::factory()->create()->id]);
        $this->charge($a, 9);

        $this->assertSame(PurchaseOrderStatus::NEAR_EXHAUSTION, PurchaseOrderStatus::describe($a->purchaseOrder)['status']);
    }

    public function test_describe_gives_the_one_day_count_that_applies_and_labels_in_both_languages(): void
    {
        $running = $this->line(['alert_percent' => 10], '2026-01-01', '2026-03-10')->purchaseOrder;
        $future = $this->line([], '2026-03-03', '2026-04-01')->purchaseOrder;
        $ended = $this->line([], '2026-01-01', '2026-02-28')->purchaseOrder;

        $d = PurchaseOrderStatus::describe($running);
        $this->assertSame(['running', 10, null, null], [$d['timeline'], $d['days_remaining'], $d['days_until_start'], $d['days_since_end']]);
        $this->assertSame('10 days left', $d['days_label']);
        $this->assertSame('In force', $d['status_label']);

        $d = PurchaseOrderStatus::describe($future);
        $this->assertSame(['not_started', null, 2, null], [$d['timeline'], $d['days_remaining'], $d['days_until_start'], $d['days_since_end']]);
        $this->assertSame('Starts in 2 days', $d['days_label']);

        $d = PurchaseOrderStatus::describe($ended);
        $this->assertSame(['ended', null, null, 1], [$d['timeline'], $d['days_remaining'], $d['days_until_start'], $d['days_since_end']]);
        $this->assertSame('Ended 1 day ago', $d['days_label']);
        $this->assertSame('Fully exhausted', $d['status_label']);
        $this->assertSame('Ended', $d['timeline_label']);

        App::setLocale('ar');
        $this->assertSame('منتهي منذ يوم واحد', PurchaseOrderStatus::describe($ended)['days_label']);
        $this->assertSame('نفذت بالكامل', PurchaseOrderStatus::describe($ended)['status_label']);
        $this->assertSame('يبدأ بعد يومين', PurchaseOrderStatus::describe($future)['days_label']);
        $this->assertSame('لم يبدأ بعد', PurchaseOrderStatus::describe($future)['timeline_label']);
        $this->assertSame('سارية', PurchaseOrderStatus::describe($running)['status_label']);
        $this->assertSame('متبقٍّ 10 أيام', PurchaseOrderStatus::describe($running)['days_label']);
    }

    public function test_describe_lists_every_line_with_its_components(): void
    {
        $line = $this->line(['max_trips' => 10]);
        $this->charge($line, 3);

        $services = PurchaseOrderStatus::describe($line->purchaseOrder)['services'];

        $this->assertSame([$line->id], array_keys($services));
        $this->assertSame(PurchaseOrderStatus::VALID, $services[$line->id]['status']);
        $this->assertSame(['state' => 'ok', 'used' => 3, 'max' => 10, 'remaining' => 7, 'percent_remaining' => 70], $services[$line->id]['components']['trips']);
    }

    public function test_the_balance_helpers_follow_the_status_rules(): void
    {
        $line = $this->line(['max_trips' => 10, 'alert_percent' => 20]);
        $this->charge($line, 9);
        $balance = QuotaBalances::for($line);

        $this->assertTrue($balance->isNearExhaustion());
        $this->assertFalse($balance->isExhausted());

        $this->charge($line, 1);
        $balance = QuotaBalances::for($line);
        $this->assertFalse($balance->isNearExhaustion());
        $this->assertTrue($balance->isExhausted());
    }

    public function test_many_lines_are_read_in_one_query(): void
    {
        $lines = collect(range(1, 5))->map(fn () => $this->line([], '2026-01-01', '2026-05-30', null, []));
        foreach ($lines as $line) {
            $this->charge($line, 2);
        }

        $fresh = PurchaseOrderService::with('purchaseOrder')->get();
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $balances = QuotaBalances::forMany($fresh);

        $this->assertSame(1, $queries);
        $this->assertCount(5, $balances);
        $this->assertSame([2], array_values(array_unique(array_map(fn ($b) => $b->tripsUsed, $balances))));
    }
}
