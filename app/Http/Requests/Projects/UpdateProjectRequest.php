<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;

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
        return $this->baseRules();
    }
}
