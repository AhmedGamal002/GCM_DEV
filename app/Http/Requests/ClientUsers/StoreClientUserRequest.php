<?php

namespace App\Http\Requests\ClientUsers;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * FRD V01.14 §1.4 "إنشاء مستخدم جديد تابع لاحد العملاء": a Project Manager
 * or Project Auditor account of one client company, with access to either
 * "all projects" of that company (current and future) or a chosen set.
 *
 * Client accounts are created exclusively through this endpoint — never the
 * generic /users form (its role whitelist is GCM staff only), same split as
 * drivers.
 *
 * The company must be one of the tenant's ACTIVE companies (a deactivated
 * one "is no longer offered for new work"), and every chosen project must
 * belong to it. Signature / stamp images are private uploads (they end up on
 * the trip documents the client approves).
 */
class StoreClientUserRequest extends FormRequest
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
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'on_vacation', 'deactivated'])],
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')
                    ->where('tenant_id', app('tenant')->id)
                    ->where('operational_status', 'active'),
            ],
        ];
    }

    /**
     * Shared with UpdateClientUserRequest so create and edit can't drift.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'max:32',
                Rule::unique('users', 'phone')
                    ->where(fn ($q) => $q->where('tenant_id', app('tenant')->id))
                    ->ignore($this->ignoredUserId()),
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'stamp' => ['nullable', 'image', 'max:2048'],
            'role' => ['required', Rule::in(User::CLIENT_ROLES)],
            'additional_data' => ['nullable', 'string'],
            'projects_scope' => ['required', Rule::in(['all', 'specific'])],
            'project_ids' => ['required_if:projects_scope,specific', 'array'],
            'project_ids.*' => ['integer', 'distinct'],
        ];
    }

    protected function ignoredUserId(): ?int
    {
        return null;
    }

    /**
     * Projects the account may be given: the chosen company's own, and
     * active ones (on edit an already-assigned project stays allowed even
     * if it has since been deactivated — see UpdateClientUserRequest).
     *
     * @return array<int, int>
     */
    protected function allowedProjectIds(int $companyId): array
    {
        return Project::query()
            ->where('company_id', $companyId)
            ->where('operational_status', 'active')
            ->pluck('id')
            ->all();
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('projects_scope') !== 'specific' || $validator->errors()->hasAny(['project_ids', 'project_ids.*', 'company_id'])) {
                return;
            }

            $ids = array_map('intval', (array) $this->input('project_ids', []));

            if ($ids === []) {
                $validator->errors()->add('project_ids', __('Choose at least one project.'));

                return;
            }

            if (array_diff($ids, $this->allowedProjectIds((int) $this->input('company_id'))) !== []) {
                $validator->errors()->add('project_ids', __('Choose only active projects of the selected company.'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'company_id.exists' => __('Choose one of the active client companies.'),
        ];
    }
}
