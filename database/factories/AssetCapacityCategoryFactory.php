<?php

namespace Database\Factories;

use App\Models\AssetCapacityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCapacityCategory>
 */
class AssetCapacityCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'applies_to' => fake()->randomElement(['container', 'tank', 'both']),
            'capacity_cbm' => fake()->randomFloat(2, 4, 30),
            'capacity_ton' => fake()->randomFloat(2, 1, 15),
            'additional_data' => null,
        ];
    }

    public function container(): static
    {
        return $this->state(['applies_to' => 'container']);
    }

    public function tank(): static
    {
        return $this->state(['applies_to' => 'tank']);
    }
}
