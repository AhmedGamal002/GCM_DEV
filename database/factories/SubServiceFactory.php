<?php

namespace Database\Factories;

use App\Models\MainService;
use App\Models\SubService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubService>
 */
class SubServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'main_service_id' => MainService::factory(),
            'name' => fake()->unique()->words(2, true).' collection',
            'asset_type' => 'container',
            'requires_recycle_receipt' => false,
            'skip_color_code' => null,
            'operational_status' => 'active',
            'additional_data' => null,
        ];
    }

    public function forService(MainService $main): static
    {
        return $this->state(['main_service_id' => $main->id]);
    }

    public function deactivated(): static
    {
        return $this->state(['operational_status' => 'deactivated']);
    }
}
