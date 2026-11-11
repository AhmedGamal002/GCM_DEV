<?php

namespace App\Domain\PurchaseOrders\Quota;

/**
 * What is left on one PO line, read from the ledger (QuotaBalances::for()).
 * Read-only. Money is in SAR with two decimals; the comparisons work in
 * whole halalas so 0.1 + 0.2 never lands just under a limit.
 *
 * A balance can go negative: a trip is recorded after it happened, so it is
 * deducted from the line the user picked even past its limits (decision ب6)
 * — canCover() is what a caller asks before that, it never blocks anything.
 *
 * The date part: `totalDays` is the PO's whole duration, both ends included.
 * While the PO runs, `daysRemaining` counts the days left including today (1
 * on the last day); before it starts it is the whole duration and
 * `daysUntilStart` says how far off the start is; once it ended it is 0 and
 * `daysSinceEnd` says how long ago.
 *
 * Whether the line is valid / about to run out / exhausted is decided in
 * PurchaseOrderStatus (decisions ب8 and ب9), and when it stops blocking a new
 * PO in ServiceExhaustion (ب5) — this class only carries the numbers.
 */
final class QuotaBalance
{
    public const TIMELINE_NOT_STARTED = 'not_started';

    public const TIMELINE_RUNNING = 'running';

    public const TIMELINE_ENDED = 'ended';

    public function __construct(
        public readonly int $tripsUsed,
        public readonly int $tripsMax,
        public readonly float $tripCostUsed,
        public readonly float $tripCostMax,
        public readonly float $ancillaryUsed,
        public readonly float $ancillaryMax,
        public readonly int $daysRemaining,
        public readonly int $totalDays,
        public readonly string $timeline,
        public readonly ?int $daysUntilStart,
        public readonly ?int $daysSinceEnd,
        public readonly int $alertPercent,
        public readonly bool $tracksAncillary,
    ) {}

    public function tripsRemaining(): int
    {
        return $this->tripsMax - $this->tripsUsed;
    }

    public function tripCostRemaining(): float
    {
        return self::fromCents(self::cents($this->tripCostMax) - self::cents($this->tripCostUsed));
    }

    public function ancillaryRemaining(): float
    {
        return self::fromCents(self::cents($this->ancillaryMax) - self::cents($this->ancillaryUsed));
    }

    /**
     * Whether one more trip at these costs fits in what is left: one trip,
     * the trip cost, and — when the line counts it — the ancillary cost. The
     * dates are not part of it (a trip is recorded after the fact).
     */
    public function canCover(float $tripCost, float $ancillaryCost = 0.0): bool
    {
        if ($this->tripsRemaining() < 1) {
            return false;
        }

        if (self::cents($this->tripCostRemaining()) < self::cents($tripCost)) {
            return false;
        }

        return ! $this->tracksAncillary
            || self::cents($this->ancillaryRemaining()) >= self::cents($ancillaryCost);
    }

    /** No balance left for new trips — one component ran out (ServiceExhaustion, decision ب5). */
    public function isExhausted(): bool
    {
        return ServiceExhaustion::isExhausted($this);
    }

    /** "على وشك النفاذ" — a component is under the line's alert percentage (PurchaseOrderStatus, ب9). */
    public function isNearExhaustion(): bool
    {
        return PurchaseOrderStatus::line($this) === PurchaseOrderStatus::NEAR_EXHAUSTION;
    }

    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }
}
