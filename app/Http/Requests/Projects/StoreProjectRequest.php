<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.12.2 "Create new project" form.
 *
 * The owning company must be one of the tenant's ACTIVE companies — a
 * deactivated company "is no longer offered for new work". The "project
 * representative account" field is not offered yet: it picks one of the
 * project's own user accounts, and client accounts don't exist until
 * FRD §1.4 is built.
 */
class StoreProjectRequest extends FormRequest
{
    use NormalizesRichTextInput;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')
                    ->where('tenant_id', app('tenant')->id)
                    ->where('operational_status', 'active'),
            ],
            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }

    /**
     * Shared with UpdateProjectRequest so create and edit can't drift.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'operational_region' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'location_url' => ['nullable', 'url:http,https', 'max:2048'],
            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.exists' => __('Choose one of the active client companies.'),
        ];
    }
}
