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
            'capacity_cbm' => fake()->randomFloat(2, 4, 30),
            'capacity_ton' => fake()->randomFloat(2, 1, 15),
        ];
    }
}
