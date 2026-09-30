<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Factories build models unguarded, so company_id (outside
            // $fillable) can be set here. Pass ->for($company) or
            // ['company_id' => …] to pick the owner.
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(3, true).' project',
            'operational_region' => fake()->optional()->city(),
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
        return $this->afterMaking(fn (Project $p) => $p->forceFill(['operational_status' => 'deactivated']));
    }
}
