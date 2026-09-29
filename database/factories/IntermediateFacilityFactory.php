<?php

namespace Database\Factories;

use App\Models\IntermediateFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntermediateFacility>
 */
class IntermediateFacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Facility',
            // Unique per tenant; random letters keep collisions rare across a test run.
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'environmental_service' => 'disposal',
            'recycling_efficiency' => null,
            'address' => null,
            'location_url' => null,
            'contract_number' => null,
            'contract_start' => null,
            'contract_end' => null,
            'additional_data' => null,
        ];
    }

    public function disposal(): static
    {
        return $this->state(['environmental_service' => 'disposal', 'recycling_efficiency' => null]);
    }

    public function sewage(): static
    {
        return $this->state(['environmental_service' => 'sewage_treatment', 'recycling_efficiency' => null]);
    }

    public function recycling(float $efficiency = 65): static
    {
        return $this->state(['environmental_service' => 'recycle', 'recycling_efficiency' => $efficiency]);
    }

    /**
     * operational_status is outside $fillable (only the status action sets
     * it), so this state force-fills it after the model is built.
     */
    public function deactivated(): static
    {
        return $this->afterMaking(fn (IntermediateFacility $f) => $f->forceFill(['operational_status' => 'deactivated']));
    }
}
