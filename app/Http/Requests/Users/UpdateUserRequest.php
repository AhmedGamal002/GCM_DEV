<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Rules\OnlyOneSystemAdminPerTenantRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    private ?User $targetUser = null;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'additional_data' => ['nullable', 'string'],
            'roles' => ['required', 'array', 'min:1', new OnlyOneSystemAdminPerTenantRule($this->targetUser()->id)],
            'roles.*' => ['string', Rule::in(['data_entry', 'auditor'])],
        ];
    }
}
