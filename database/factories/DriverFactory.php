<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 *
 * A driver: a `driver`-role account (the roles must be seeded — RoleSeeder)
 * with its drivers row and valid documents. GCM's own unless ->forContractor().
 * Builds the rows directly; tests of the driver forms go through the API.
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn () => ['phone' => fake()->numerify('+9665########')]),
            'contractor_id' => null,
            'residence_number' => fake()->numerify('RES-#####'),
            'residence_valid_to' => now()->addYears(2)->toDateString(),
            'license_number' => fake()->numerify('LIC-#####'),
            'license_valid_to' => now()->addYears(2)->toDateString(),
            'operational_license_number' => fake()->numerify('OPL-#####'),
            'operational_license_valid_to' => now()->addYears(2)->toDateString(),
            'insurance_number' => fake()->numerify('INS-#####'),
            'insurance_valid_to' => now()->addYears(2)->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Driver $driver) => $driver->user->assignRole('driver'));
    }

    public function forContractor(Contractor $contractor): static
    {
        return $this->state(fn () => [
            'contractor_id' => $contractor->id,
            'user_id' => User::factory()->state([
                'affiliation' => 'contractor',
                'contractor_id' => $contractor->id,
                'phone' => fake()->numerify('+9665########'),
            ]),
        ]);
    }
}
