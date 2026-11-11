<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;

/**
 * The computed state of a PO and of each of its lines — decisions ب8 and ب9
 * (2026-11-09), in one place.
 *
 * A line has up to four COMPONENTS: the date, the trips, the trip-cost total
 * and (when the line counts it) the ancillary-cost total. Each is
 *  - exhausted — nothing left (the PO ended, or the used part reached the max);
 *  - near      — what is left is under the LINE'S alert percentage of its max
 *                (strictly under: "متبقي فيه أقل من حد التنبيه"). For the
 *                date it is the days left out of the PO's whole duration, and
 *                each cost total is watched on its own (ب9);
 *  - ok        — otherwise (a PO that hasn't started has its date ok).
 *
 * A LINE ("بند") is
 *  - exhausted        — one component ran out: it has no balance left for new
 *                       trips (the same test as ServiceExhaustion, ب5);
 *  - near_exhaustion  — "على وشك النفاذ": a component is near;
 *  - valid            — "سارية".
 *
 * The PO's ITEMS STATUS ("الحالة العامة للبنود") is
 *  - exhausted        — "نفذت بالكامل": EVERY line is exhausted;
 *  - near_exhaustion  — any line is near or exhausted (one exhausted line on
 *                       its own only makes the PO "about to run out", ب8);
 *  - valid            — every line is valid.
 *
 * Next to it the PO's TIMELINE (ب8): not_started (with the days until it
 * starts), running (with the days left) or ended ("ended N days ago").
 *
 * Reading (not stated by the client, change it here if they say otherwise):
 * "exhausted = every component of every service" is read as every LINE
 * having run out (each line runs out with its first exhausted component),
 * so a PO whose dates ended is "fully exhausted" too — its lines have no
 * balance left for new trips — and its timeline says since when.
 */
final class PurchaseOrderStatus
{
    public const VALID = 'valid';

    public const NEAR_EXHAUSTION = 'near_exhaustion';

    public const EXHAUSTED = 'exhausted';

    public const STATUSES = [self::VALID, self::NEAR_EXHAUSTION, self::EXHAUSTED];

    public const COMPONENT_OK = 'ok';

    public const COMPONENT_NEAR = 'near';

    public const COMPONENT_EXHAUSTED = 'exhausted';

    /**
     * Each tracked component of a line with its state and numbers. Amounts
     * are SAR; `percent_remaining` is 0–100 (what a progress bar fills).
     *
     * @return array<string, array{state: string, used: int|float, max: int|float, remaining: int|float, percent_remaining: int}>
     */
    public static function components(QuotaBalance $b): array
    {
        $out = [];

        $out[ServiceExhaustion::DATE] = self::dateComponent($b);

        $out[ServiceExhaustion::TRIPS] = self::component($b->tripsUsed, $b->tripsMax, $b->tripsRemaining(), $b->tripsRemaining(), $b->tripsMax, $b->alertPercent);

        if ($b->tripCostMax > 0) {
            $out[ServiceExhaustion::TRIP_COST] = self::component(
                $b->tripCostUsed, $b->tripCostMax, $b->tripCostRemaining(),
                QuotaBalance::cents($b->tripCostRemaining()), QuotaBalance::cents($b->tripCostMax), $b->alertPercent,
            );
        }

        if ($b->tracksAncillary && $b->ancillaryMax > 0) {
            $out[ServiceExhaustion::ANCILLARY_COST] = self::component(
                $b->ancillaryUsed, $b->ancillaryMax, $b->ancillaryRemaining(),
                QuotaBalance::cents($b->ancillaryRemaining()), QuotaBalance::cents($b->ancillaryMax), $b->alertPercent,
            );
        }

        return $out;
    }

    /** The status of one line: valid / near_exhaustion / exhausted. */
    public static function line(QuotaBalance $b): string
    {
        if (ServiceExhaustion::isExhausted($b)) {
            return self::EXHAUSTED;
        }

        foreach (self::components($b) as $component) {
            if ($component['state'] !== self::COMPONENT_OK) {
                return self::NEAR_EXHAUSTION;
            }
        }

        return self::VALID;
    }

    /**
     * The PO's items status from the balances of its lines.
     *
     * @param  iterable<QuotaBalance>  $balances
     */
    public static function order(iterable $balances): string
    {
        $statuses = [];
        foreach ($balances as $balance) {
            $statuses[] = self::line($balance);
        }

        if ($statuses === []) {
            return self::VALID;
        }

        if (count(array_unique($statuses)) === 1 && $statuses[0] === self::EXHAUSTED) {
            return self::EXHAUSTED;
        }

        return in_array(self::NEAR_EXHAUSTION, $statuses, true) || in_array(self::EXHAUSTED, $statuses, true)
            ? self::NEAR_EXHAUSTION
            : self::VALID;
    }

    /**
     * Everything a PO page shows about its state, with the labels translated
     * on the server (like ExpiryWarning::describe()): the items status, the
     * timeline with the one day count that applies to it, and every line.
     *
     * @return array{status: string, status_label: string, timeline: string, timeline_label: string, days_remaining: ?int, days_until_start: ?int, days_since_end: ?int, days_label: string, services: array<int, array<string, mixed>>}
     */
    public static function describe(PurchaseOrder $po, ?CarbonInterface $asOf = null): array
    {
        $balances = QuotaBalances::forMany($po->services, $asOf);
        $dates = QuotaBalances::dates($po->starts_on, $po->ends_on, ($asOf ?? now())->copy()->startOfDay());
        $status = self::order($balances);

        return [
            'status' => $status,
            'status_label' => self::orderLabel($status),
            'timeline' => $dates['timeline'],
            'timeline_label' => self::timelineLabel($dates['timeline']),
            'days_remaining' => $dates['timeline'] === QuotaBalance::TIMELINE_RUNNING ? $dates['days_remaining'] : null,
            'days_until_start' => $dates['days_until_start'],
            'days_since_end' => $dates['days_since_end'],
            'days_label' => self::daysLabel($dates['timeline'], $dates['days_remaining'], $dates['days_until_start'], $dates['days_since_end']),
            'services' => array_map(fn (QuotaBalance $b) => self::describeLine($b), $balances),
        ];
    }

    /** @return array{status: string, status_label: string, components: array<string, array<string, mixed>>} */
    public static function describeLine(QuotaBalance $b): array
    {
        $status = self::line($b);

        return [
            'status' => $status,
            'status_label' => self::lineLabel($status),
            'components' => self::components($b),
        ];
    }

    public static function orderLabel(string $status): string
    {
        return match ($status) {
            self::EXHAUSTED => __('Fully exhausted'),
            self::NEAR_EXHAUSTION => __('About to run out'),
            default => __('In force'),
        };
    }

    public static function lineLabel(string $status): string
    {
        return match ($status) {
            self::EXHAUSTED => __('Exhausted'),
            self::NEAR_EXHAUSTION => __('About to run out'),
            default => __('In force'),
        };
    }

    public static function timelineLabel(string $timeline): string
    {
        return match ($timeline) {
            QuotaBalance::TIMELINE_NOT_STARTED => __('Not started yet'),
            QuotaBalance::TIMELINE_ENDED => __('Ended'),
            default => __('Running'),
        };
    }

    public static function daysLabel(string $timeline, int $daysRemaining, ?int $daysUntilStart, ?int $daysSinceEnd): string
    {
        return match ($timeline) {
            QuotaBalance::TIMELINE_NOT_STARTED => trans_choice('{1} Starts in :count day|[2,*] Starts in :count days', $daysUntilStart),
            QuotaBalance::TIMELINE_ENDED => trans_choice('{1} Ended :count day ago|[2,*] Ended :count days ago', $daysSinceEnd),
            default => trans_choice('{1} :count day left|[2,*] :count days left', $daysRemaining),
        };
    }

    private static function dateComponent(QuotaBalance $b): array
    {
        $state = match (true) {
            $b->timeline === QuotaBalance::TIMELINE_ENDED => self::COMPONENT_EXHAUSTED,
            $b->timeline === QuotaBalance::TIMELINE_RUNNING && $b->daysRemaining * 100 < $b->alertPercent * $b->totalDays => self::COMPONENT_NEAR,
            default => self::COMPONENT_OK,
        };

        return [
            'state' => $state,
            'used' => $b->totalDays - $b->daysRemaining,
            'max' => $b->totalDays,
            'remaining' => $b->daysRemaining,
            'percent_remaining' => self::percent($b->daysRemaining, $b->totalDays),
        ];
    }

    /**
     * @param  int  $remainingUnits  what is left in whole units (trips, or halalas for money)
     * @param  int  $maxUnits  the maximum in the same units
     */
    private static function component(int|float $used, int|float $max, int|float $remaining, int $remainingUnits, int $maxUnits, int $alertPercent): array
    {
        $state = match (true) {
            $remainingUnits <= 0 => self::COMPONENT_EXHAUSTED,
            $remainingUnits * 100 < $alertPercent * $maxUnits => self::COMPONENT_NEAR,
            default => self::COMPONENT_OK,
        };

        return [
            'state' => $state,
            'used' => $used,
            'max' => $max,
            'remaining' => $remaining,
            'percent_remaining' => self::percent($remainingUnits, $maxUnits),
        ];
    }

    private static function percent(int|float $remaining, int|float $max): int
    {
        if ($max <= 0 || $remaining <= 0) {
            return 0;
        }

        return (int) min(100, floor($remaining / $max * 100));
    }
}
