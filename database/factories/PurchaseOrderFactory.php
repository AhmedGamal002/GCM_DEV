<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 *
 * Builds the row directly (no rules run) — tests that exercise the rules go
 * through CreatePurchaseOrderAction. Pass ->forProject($project) to tie it to
 * an existing project; the number is the company prefix + a random paper
 * number unless given.
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::find($attributes['project_id'])->company_id,
            'number' => fn (array $attributes) => PurchaseOrder::composeNumber(
                Company::find($attributes['company_id'])->prefix,
                (string) fake()->unique()->numberBetween(1000, 9999999),
            ),
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonths(5)->toDateString(),
            'region' => null,
            'operational_status' => 'active',
            'additional_data' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'company_id' => $project->company_id]);
    }

    public function between(string $startsOn, string $endsOn): static
    {
        return $this->state(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }

    public function deactivated(): static
    {
        return $this->state(['operational_status' => 'deactivated']);
    }

    /** Adds one line per call: ->withLine(['main_service_id' => …, 'max_trips' => 10]). */
    public function withLine(array $attributes = []): static
    {
        return $this->has(PurchaseOrderService::factory()->state($attributes), 'services');
    }
}
