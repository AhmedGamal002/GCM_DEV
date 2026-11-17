<?php

namespace Tests\Unit;

use App\Domain\Trips\Support\WasteQuantityConverter;
use Tests\TestCase;

/** Decision ج10: one factor (1 m³ = 0.3125 t) and three decimals. */
class WasteQuantityConverterTest extends TestCase
{
    public function test_the_factor_and_decimals_come_from_config(): void
    {
        $this->assertSame(['tonnes_per_cubic_meter' => 0.3125, 'decimals' => 3], WasteQuantityConverter::toArray());
    }

    public function test_conversions_both_ways_round_to_three_decimals(): void
    {
        $this->assertSame(3.75, WasteQuantityConverter::tonnesFromCubicMeters(12));
        $this->assertSame(12.0, WasteQuantityConverter::cubicMetersFromTonnes(3.75));
        $this->assertSame(2.188, WasteQuantityConverter::tonnesFromCubicMeters(7));
        $this->assertSame(3.2, WasteQuantityConverter::cubicMetersFromTonnes(1));
        $this->assertSame(0.333, WasteQuantityConverter::round(1 / 3));
    }

    public function test_complete_fills_the_missing_unit_and_keeps_a_manual_pair(): void
    {
        $this->assertSame(['ton' => null, 'cbm' => null], WasteQuantityConverter::complete(null, null));
        $this->assertSame(['ton' => 2.0, 'cbm' => 6.4], WasteQuantityConverter::complete(2, null));
        $this->assertSame(['ton' => 3.125, 'cbm' => 10.0], WasteQuantityConverter::complete(null, 10));
        $this->assertSame(['ton' => 2.0, 'cbm' => 7.5], WasteQuantityConverter::complete(2, 7.5));
    }

    public function test_a_different_factor_in_config_is_used_everywhere(): void
    {
        config(['trips.tonnes_per_cubic_meter' => 0.5]);

        $this->assertSame(5.0, WasteQuantityConverter::tonnesFromCubicMeters(10));
        $this->assertSame(['ton' => 1.0, 'cbm' => 2.0], WasteQuantityConverter::complete(1, null));
    }
}
