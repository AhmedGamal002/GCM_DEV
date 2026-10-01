<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\User;
use Closure;

/**
 * FRD V01.14 §1.12.4: every project field is editable by (System Admin /
 * Data Entry) EXCEPT the owning company — the project's ID is built from
 * the company's short name, so it is locked after creation (a
 * `company_id` in the payload is simply not validated or saved).
 * Deactivation has its own endpoint (UpdateProjectStatusRequest).
 *
 * Not route-model-bound (the `{project}` param is a raw id — see
 * ProjectController's docblock).
 */
class UpdateProjectRequest extends StoreProjectRequest
{
    private ?Project $targetProject = null;

    private function targetProject(): Project
    {
        return $this->targetProject ??= Project::findOrFail($this->route('project'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetProject());
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'representative_id' => ['nullable', 'integer', $this->representativeRule()],
        ];
    }

    /**
     * Any active client account that can see this project qualifies. An
     * unchanged value is always accepted so saving other fields still works
     * after the rep was later put on vacation / deactivated.
     */
    protected function representativeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $project = $this->targetProject();

            if ((int) $value === $project->representative_id) {
                return;
            }

            $user = User::find($value);

            if (! $user || ! $user->isClient() || $user->status !== 'active' || ! $project->isVisibleTo($user)) {
                $fail(__('Choose an active client account that has access to this project.'));
            }
        };
    }
}
