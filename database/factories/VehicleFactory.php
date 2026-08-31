<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plate_letters' => strtoupper(fake()->unique()->lexify('???')),
            'plate_numbers' => (string) fake()->unique()->numberBetween(1000, 9999),
            'vehicle_category_id' => fn () => VehicleCategory::query()->inRandomOrder()->value('id')
                ?? VehicleCategory::factory(),
            'has_embedded_container' => false,
            'embedded_container_type' => null,
            'embedded_asset_capacity_category_id' => null,
            'affiliation' => 'gcm',
            'additional_data' => null,
        ];
    }

    /**
     * operational_status is outside $fillable (only the status action
     * sets it), so states force-fill it after the model is built.
     */
    public function onMaintenance(): static
    {
        return $this->afterMaking(fn (Vehicle $v) => $v->forceFill(['operational_status' => 'on_maintenance']));
    }

    public function deactivated(): static
    {
        return $this->afterMaking(fn (Vehicle $v) => $v->forceFill(['operational_status' => 'deactivated']));
    }
}
