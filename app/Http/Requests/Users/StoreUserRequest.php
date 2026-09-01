<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Rules\OnlyOneSystemAdminPerTenantRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Per the FRD's "Create new user" form: only data_entry/auditor (GCM
 * staff) or driver are creatable here — system_admin is never offered
 * (exactly one per tenant, provisioned separately, not cloneable). This
 * is enforced server-side too, not just hidden in the UI.
 *
 * A driver created here gets an EMPTY `drivers` profile row (see
 * CreateUserAction) — residence/license/insurance are all nullable in
 * the schema and not required on this form, matching the FRD: those
 * details aren't mandatory at creation and are filled in later via the
 * dedicated Drivers edit page. This intentionally differs from
 * CreateDriverAction (POST /api/v1/drivers), where the same fields ARE
 * required — that's a second, more complete entry point for a driver's
 * full profile in one go, not the only way to create one. What both
 * paths guarantee is a matching Driver row so the user is never
 * invisible on the Drivers page — a real bug found live where a
 * driver-role User existed with no Driver row at all (even the seeded
 * demo driver had it — see DatabaseSeeder's fix).
 *
 * Company/Contractor-affiliated users aren't creatable yet — those
 * entities don't exist until Week 4-5. A driver's `affiliation` is
 * always 'gcm' for now (the FRD's "belongs to a Contractor" option is
 * deferred until Contractor exists).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'on_vacation', 'deactivated'])],
            'additional_data' => ['nullable', 'string'],
            'roles' => ['required', 'array', 'size:1', new OnlyOneSystemAdminPerTenantRule],
            'roles.*' => ['string', Rule::in(['data_entry', 'auditor', 'driver'])],
        ];
    }
}
