<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDocument>
 */
class VehicleDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => 'registration_card',
            'document_number' => (string) fake()->numberBetween(100000, 999999),
            'area_name' => null,
            'valid_to' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'attachment_path' => null,
        ];
    }

    public function entryPermit(): static
    {
        return $this->state(fn () => [
            'type' => 'entry_permit',
            'area_name' => fake()->city(),
        ]);
    }
}
