<?php

namespace Database\Factories;

use App\Models\MainService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MainService>
 */
class MainServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' service',
            'service_type' => 'non_hazardous',
            'operational_status' => 'active',
            'additional_data' => null,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(['service_type' => $type]);
    }

    public function supplying(): static
    {
        return $this->ofType('supplying');
    }

    public function deactivated(): static
    {
        return $this->state(['operational_status' => 'deactivated']);
    }
}
