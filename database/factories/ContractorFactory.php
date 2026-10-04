<?php

namespace Database\Factories;

use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'trading_name' => fake()->optional()->company(),
            'phone' => fake()->optional()->numerify('+9665########'),
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
        return $this->afterMaking(fn (Contractor $c) => $c->forceFill(['operational_status' => 'deactivated']));
    }
}
