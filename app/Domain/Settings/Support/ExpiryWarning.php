<?php

namespace App\Domain\Settings\Support;

use App\Models\TenantSetting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Is this document about to expire?" — one place for the thresholds and for how a `valid_to` date is
 * turned into the state the progress bars on the driver and vehicle pages draw.
 *
 * The thresholds are two per-tenant settings (days), changed by the system admin on the General Settings
 * page: one for driver records (residence, licenses, insurance, entry permits) and one for vehicle
 * records (documents and entry permits). A document with that many days left — or fewer — is "warning"
 * (red); before that it is "ok" (green); once the date has passed it is "expired".
 *
 * Registered as a singleton, so the settings are read once per request however many documents a page lists.
 */
class ExpiryWarning
{
    public const DEFAULT_DAYS = 30;

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 365;

    public const SCOPE_DRIVER = 'driver';

    public const SCOPE_VEHICLE = 'vehicle';

    private const KEYS = [
        self::SCOPE_DRIVER => 'driver_expiry_warning_days',
        self::SCOPE_VEHICLE => 'vehicle_expiry_warning_days',
    ];

    /** @var array<string, string>|null */
    private ?array $stored = null;

    public static function key(string $scope): string
    {
        return self::KEYS[$scope];
    }

    /** The warning threshold, in days, for `driver` or `vehicle` records. */
    public function days(string $scope): int
    {
        $this->stored ??= TenantSetting::query()
            ->whereIn('key', array_values(self::KEYS))
            ->pluck('value', 'key')
            ->all();

        $value = $this->stored[self::KEYS[$scope]] ?? null;

        return is_numeric($value) ? max(self::MIN_DAYS, (int) $value) : self::DEFAULT_DAYS;
    }

    /** Forget what was read — called after the settings are saved. */
    public function refresh(): void
    {
        $this->stored = null;
    }

    /**
     * The state of a document that is valid until `$validTo`, or null when it has no date.
     *
     * `status` is `ok` (tag "Valid", green bar), `warning` (tag "Need review", yellow bar — the days left
     * reached the threshold) or `expired` (tag "Expired", empty red bar). `percent` is what the bar fills:
     * full while it is green; once yellow it shrinks day by day from the threshold down to nothing.
     *
     * @return array{days_remaining: int, status: string, status_label: string, percent: int, label: string}|null
     */
    public function describe(?CarbonInterface $validTo, string $scope): ?array
    {
        if ($validTo === null) {
            return null;
        }

        $threshold = $this->days($scope);
        $daysRemaining = (int) now()->startOfDay()->diffInDays($validTo->copy()->startOfDay(), false);

        if ($daysRemaining < 0) {
            return [
                'days_remaining' => $daysRemaining,
                'status' => 'expired',
                'status_label' => __('Expired'),
                'percent' => 0,
                'label' => trans_choice('{1} Expired :count day ago|[2,*] Expired :count days ago', abs($daysRemaining)),
            ];
        }

        $warning = $daysRemaining <= $threshold;

        return [
            'days_remaining' => $daysRemaining,
            'status' => $warning ? 'warning' : 'ok',
            'status_label' => $warning ? __('Need review') : __('Valid'),
            'percent' => $warning ? (int) max(4, round($daysRemaining / $threshold * 100)) : 100,
            'label' => trans_choice('{0} Expires today|{1} :count day left|[2,*] :count days left', $daysRemaining),
        ];
    }

    /** The values the lists' "Documents Status" filter accepts — the same ones `overall()` returns. */
    public const STATUSES = ['ok', 'warning', 'expired'];

    /**
     * The SQL twin of overall(): keep only the rows whose overall status is `$status`, so the lists' filter
     * (and the exports, which read the same query) agree with the tag on every row.
     *
     * A date is `expired` before today, `warning` from today up to today + threshold, `ok` after that. A row is
     *   ok      — has an ok date and no expired / warning one;
     *   expired — has an expired date and no ok / warning one;
     *   warning — has a warning date, or an expired date next to an ok one.
     * Rows with no dated document match none of them.
     *
     * @param  list<string>  $columns  the model's own date columns (qualified with its table)
     * @param  string  $relation  the has-many relation whose `valid_to` also counts (permits / documents)
     */
    public function filterByOverallStatus(Builder $query, string $status, string $scope, array $columns, string $relation): Builder
    {
        $today = now()->toDateString();
        // End of the last warning day, not its start: an Eloquent `date` attribute is saved as 'Y-m-d 00:00:00' on
        // SQLite (the tests' DB) where dates compare as plain strings, so '2026-11-05 00:00:00' would sort after
        // a bare '2026-11-05'. MySQL's DATE column behaves the same way either way.
        $limit = now()->addDays($this->days($scope))->toDateString().' 23:59:59';

        $apply = function ($q, string $column, string $kind) use ($today, $limit) {
            match ($kind) {
                'expired' => $q->where($column, '<', $today),
                'warning' => $q->whereBetween($column, [$today, $limit]),
                default => $q->where($column, '>', $limit),
            };
        };

        // "any of the dates is <kind>" — own columns or any row of the relation.
        $any = fn (string $kind) => function ($q) use ($columns, $relation, $apply, $kind) {
            foreach ($columns as $column) {
                $q->orWhere(fn ($w) => $apply($w, $column, $kind));
            }
            $q->orWhereHas($relation, fn ($r) => $apply($r, 'valid_to', $kind));
        };

        return match ($status) {
            'ok' => $query->where($any('ok'))->whereNot($any('expired'))->whereNot($any('warning')),
            'expired' => $query->where($any('expired'))->whereNot($any('ok'))->whereNot($any('warning')),
            'warning' => $query->where(fn ($q) => $q
                ->where($any('warning'))
                ->orWhere(fn ($m) => $m->where($any('expired'))->where($any('ok')))),
            default => $query,
        };
    }

    /**
     * The one status shown in a driver's / vehicle's list row, from all of its dated documents and permits:
     *
     *  - any of them is about to expire (yellow)            → `warning`  "Need review"
     *  - all of them have expired                           → `expired`  "Expired"
     *  - all of them are still valid                        → `ok`       "Valid"
     *  - some expired and the rest valid (none in yellow)   → `warning`  — a lapsed document next to valid ones
     *    still needs somebody to look at it, so it is never reported as plain "Valid"
     *
     * Documents without a date are ignored; null when there is no dated document at all.
     *
     * @param  iterable<CarbonInterface|null>  $dates
     * @return array{status: string, status_label: string}|null
     */
    public function overall(iterable $dates, string $scope): ?array
    {
        $statuses = [];

        foreach ($dates as $date) {
            if ($described = $this->describe($date, $scope)) {
                $statuses[] = $described['status'];
            }
        }

        if ($statuses === []) {
            return null;
        }

        $status = match (true) {
            in_array('warning', $statuses, true) => 'warning',
            ! in_array('ok', $statuses, true) => 'expired',
            ! in_array('expired', $statuses, true) => 'ok',
            default => 'warning',
        };

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'ok' => __('Valid'),
                'expired' => __('Expired'),
                default => __('Need review'),
            },
        ];
    }
}
