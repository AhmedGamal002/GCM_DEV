<?php

namespace App\Domain\Trips\Support;

/**
 * Decision ج10 (2026-11-10): a trip's waste quantity in tonnes and cubic
 * metres. The user types one and the other is computed with ONE factor for
 * every kind of waste — 1 m³ = 0.3125 t, the FRD's own line (liquid waste in
 * tanks included) — and can still correct it by hand. Three decimals for
 * storage and display. Used by the trip actions (7.1), the forms (they read
 * toArray() from the API — never a factor hard-coded in JS) and the
 * sustainability report (8.5).
 */
final class WasteQuantityConverter
{
    public static function factor(): float
    {
        return (float) config('trips.tonnes_per_cubic_meter', 0.3125);
    }

    public static function decimals(): int
    {
        return (int) config('trips.quantity_decimals', 3);
    }

    public static function round(float $value): float
    {
        return round($value, self::decimals());
    }

    public static function tonnesFromCubicMeters(float $cbm): float
    {
        return self::round($cbm * self::factor());
    }

    public static function cubicMetersFromTonnes(float $tonnes): float
    {
        return self::round($tonnes / self::factor());
    }

    /**
     * Both units from what the user entered: the missing one is computed,
     * one given alone is kept as typed (rounded), both given are kept as
     * typed (the manual correction). Nothing given → both null.
     *
     * @return array{ton: ?float, cbm: ?float}
     */
    public static function complete(?float $ton, ?float $cbm): array
    {
        if ($ton === null && $cbm === null) {
            return ['ton' => null, 'cbm' => null];
        }

        return [
            'ton' => $ton !== null ? self::round($ton) : self::tonnesFromCubicMeters($cbm),
            'cbm' => $cbm !== null ? self::round($cbm) : self::cubicMetersFromTonnes($ton),
        ];
    }

    /** @return array{tonnes_per_cubic_meter: float, decimals: int} for the forms (via the API) */
    public static function toArray(): array
    {
        return ['tonnes_per_cubic_meter' => self::factor(), 'decimals' => self::decimals()];
    }
}
