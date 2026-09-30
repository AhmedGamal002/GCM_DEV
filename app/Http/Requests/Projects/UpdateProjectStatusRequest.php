<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectStatusRequest extends FormRequest
{
    private ?Project $targetProject = null;

    private function targetProject(): Project
    {
        return $this->targetProject ??= Project::findOrFail($this->route('project'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetProject());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
