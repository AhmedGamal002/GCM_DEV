<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'business_sector' => fake()->optional()->randomElement(['Construction', 'Oil & Gas', 'Real estate', 'Retail']),
            'phone' => fake()->optional()->numerify('+9665########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => null,
            'location_url' => null,
            'additional_data' => null,
        ];
    }

    /**
     * operational_status is outside $fillable (only the status action
     * sets it), so this state force-fills it after the model is built.
     */
    public function deactivated(): static
    {
        return $this->afterMaking(fn (Company $c) => $c->forceFill(['operational_status' => 'deactivated']));
    }
}
