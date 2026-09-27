<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Rules\OnlyOneSystemAdminPerTenantRule;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Same 'driver' exclusion as StoreUserRequest — see its docblock. Editing
 * an existing driver's role/data goes through PATCH /api/v1/drivers/{id}
 * instead; UserPolicy::update() rejects this endpoint outright for a
 * target who already hasRole('driver'), so this validation rule mainly
 * guards the OTHER direction (promoting a data_entry/auditor INTO
 * 'driver' here, which would create the same orphaned-record bug).
 *
 * Also tightened from a bare `exists:roles,...` (which allowed ANY role
 * name, including system_admin) to the same explicit whitelist as Store
 * — promoting an existing user to system_admin was never supposed to be
 * possible outside initial tenant provisioning (see StoreUserRequest's
 * docblock: "provisioned separately, not cloneable"), and the old rule
 * only ever caught a *second* system_admin, not a promotion at all.
 */
class UpdateUserRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?User $targetUser = null;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    /**
     * Not route-model-bound — the `{user}` route param is a raw id (see
     * UserController's docblock for why), so it's resolved here through
     * the normal tenant-scoped query, same as the controller does.
     */
    private function targetUser(): User
    {
        return $this->targetUser ??= User::findOrFail($this->route('user'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetUser());
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'max:32',
                Rule::unique('users', 'phone')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($this->targetUser()->id),
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
            // FRD: GCM users can't change their own password — an admin
            // (system_admin / data_entry) sets it here. Blank = unchanged.
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'additional_data' => ['nullable', 'string'],
            'roles' => ['required', 'array', 'min:1', new OnlyOneSystemAdminPerTenantRule($this->targetUser()->id)],
            'roles.*' => ['string', Rule::in(['data_entry', 'auditor'])],
        ];
    }
}
