<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A client-company account (FRD §1.4): the role, the company and the
     * project scope. Pass a specific set of projects with ->hasAttached()
     * or by attaching after creation; all_projects defaults to true.
     */
    public function client(?Company $company = null, string $role = 'client_project_manager', bool $allProjects = true): static
    {
        return $this->state(fn () => [
            'affiliation' => 'client',
            'company_id' => $company?->id ?? Company::factory(),
            'all_projects' => $allProjects,
        ])->afterCreating(fn (User $user) => $user->assignRole($role));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
