<?php

namespace App\Http\Requests\ContractorUsers;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * FRD V01.14 §1.4 "إنشاء مستخدم جديد تابع لاحد المتعهدين": a user of one
 * contractor company — name, email, mobile, password, optional photo, the
 * contractor it belongs to, status and additional data. The role is fixed
 * (`contractor_user`), so there is no role field.
 *
 * Contractor users are created exclusively through this endpoint — never the
 * generic /users form (its role whitelist is GCM staff only), same split as
 * drivers and client accounts.
 *
 * The contractor must be one of the tenant's ACTIVE contractors (a deactivated
 * one is no longer offered for new work).
 */
class StoreContractorUserRequest extends FormRequest
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
            'contractor_id' => [
                'required',
                'integer',
                Rule::exists('contractors', 'id')
                    ->where('tenant_id', app('tenant')->id)
                    ->where('operational_status', 'active'),
            ],
        ];
    }

    /**
     * Shared with UpdateContractorUserRequest so create and edit can't drift.
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
            'additional_data' => ['nullable', 'string'],
        ];
    }

    protected function ignoredUserId(): ?int
    {
        return null;
    }

    public function messages(): array
    {
        return [
            'contractor_id.exists' => __('Choose one of the active contractors.'),
        ];
    }
}
