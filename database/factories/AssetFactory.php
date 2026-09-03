<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['container', 'tank']);

        return [
            'name' => ucfirst($type).' '.fake()->unique()->bothify('##??'),
            'asset_type' => $type,
            'asset_capacity_category_id' => fn () => AssetCapacityCategory::query()->inRandomOrder()->value('id')
                ?? AssetCapacityCategory::factory(),
            'affiliation' => 'gcm',
            'contractor_id' => null,
            'purchase_date' => fake()->optional()->dateTimeBetween('-5 years', 'now'),
            'additional_data' => null,
        ];
    }

    public function container(): static
    {
        return $this->state(['asset_type' => 'container', 'name' => 'Container '.fake()->unique()->bothify('##??')]);
    }

    public function tank(): static
    {
        return $this->state(['asset_type' => 'tank', 'name' => 'Tank '.fake()->unique()->bothify('##??')]);
    }

    /**
     * operational_status is outside $fillable (only the status action
     * sets it), so states force-fill it after the model is built.
     */
    public function onMaintenance(): static
    {
        return $this->afterMaking(fn (Asset $a) => $a->forceFill(['operational_status' => 'on_maintenance']));
    }

    public function deactivated(): static
    {
        return $this->afterMaking(fn (Asset $a) => $a->forceFill(['operational_status' => 'deactivated']));
    }
}
