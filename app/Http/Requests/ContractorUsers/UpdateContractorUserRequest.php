<?php

namespace App\Http\Requests\ContractorUsers;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * FRD V01.14 §1.4: every field of a contractor user is editable by (System
 * Admin / Data Entry), the email included (it stays unique across tenants —
 * login looks accounts up by it). A blank password keeps the current one;
 * status has its own endpoint (PATCH /users/{id}/status), like every other
 * account.
 *
 * Not route-model-bound (the `{user}` param is a raw id — see
 * UserController's docblock).
 */
class UpdateContractorUserRequest extends StoreContractorUserRequest
{
    private ?User $targetUser = null;

    private function targetUser(): User
    {
        return $this->targetUser ??= User::findOrFail($this->route('user'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateContractorUser', $this->targetUser());
    }

    protected function ignoredUserId(): ?int
    {
        return $this->targetUser()->id;
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->targetUser()->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            // The account's current contractor stays valid even if it has since
            // been deactivated; a different one must be active.
            'contractor_id' => [
                'required',
                'integer',
                Rule::exists('contractors', 'id')
                    ->where('tenant_id', app('tenant')->id)
                    ->where(fn ($q) => $q->where('operational_status', 'active')
                        ->orWhere('id', $this->targetUser()->contractor_id)),
            ],
        ];
    }
}
