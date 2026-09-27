<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Rules\OnlyOneSystemAdminPerTenantRule;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Per the FRD: only data_entry/auditor (GCM staff) are creatable here —
 * system_admin is never offered (exactly one per tenant, provisioned
 * separately, not cloneable). This is enforced server-side too, not
 * just hidden in the UI.
 *
 * `driver` is NOT an allowed role here — confirmed directly against the
 * FRD text (not just inferred): "إدارة وانشاء حسابات السائقين" is its
 * own section with its own dedicated "انشاء مستخدم جديد (سائق)" page
 * (separate Reference URL, its own complete field spec — Default
 * Vehicle, residence, license, operational license, insurance, entry
 * permits, ALL required except attachments) — not a category inside
 * this general form. Driver accounts are created exclusively through
 * POST /api/v1/drivers (CreateDriverAction). An earlier version of this
 * file allowed 'driver' here (twice, in fact — this went back and forth
 * before the FRD text settled it): first removed after a live bug (a
 * driver-role User with no matching Driver row), then briefly restored
 * on a mistaken assumption that the FRD offered driver as a form
 * category with optional extra fields — it doesn't; that assumption was
 * wrong, the FRD's driver fields are marked "لازم" (required).
 *
 * Company/Contractor-affiliated users aren't creatable yet — those
 * entities don't exist until Week 4-5.
 */
class StoreUserRequest extends FormRequest
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
        $tenantId = app('tenant')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => [
                'required', 'string', 'max:32',
                Rule::unique('users', 'phone')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'on_vacation', 'deactivated'])],
            'additional_data' => ['nullable', 'string'],
            'roles' => ['required', 'array', 'size:1', new OnlyOneSystemAdminPerTenantRule],
            'roles.*' => ['string', Rule::in(['data_entry', 'auditor'])],
        ];
    }
}
