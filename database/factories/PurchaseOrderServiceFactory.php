<?php

namespace Database\Factories;

use App\Models\MainService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderService>
 *
 * A trips-only line of 100 trips at 50 SAR with a 20% alert by default (the
 * FRD's own example uses 150 × 50 and 20%).
 */
class PurchaseOrderServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'main_service_id' => MainService::factory(),
            'quota_method' => PurchaseOrderService::METHOD_TRIPS_ONLY,
            'max_trips' => 100,
            'trip_cost' => 50,
            'ancillary_cost_per_trip' => 0,
            'alert_percent' => 20,
        ];
    }

    public function withAncillary(float $perTrip = 30): static
    {
        return $this->state([
            'quota_method' => PurchaseOrderService::METHOD_TRIPS_AND_ANCILLARY,
            'ancillary_cost_per_trip' => $perTrip,
        ]);
    }
}
