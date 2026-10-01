<?php

namespace App\Http\Requests\ClientUsers;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * FRD V01.14 §1.4: every field of a client account is editable by (System
 * Admin / Data Entry) — the email being the one exception (login lookup is
 * by email, see LoginController), same as the other account forms. A blank
 * password keeps the current one; status has its own endpoint
 * (PATCH /users/{id}/status), like every other account.
 *
 * Not route-model-bound (the `{user}` param is a raw id — see
 * UserController's docblock).
 */
class UpdateClientUserRequest extends StoreClientUserRequest
{
    private ?User $targetUser = null;

    private function targetUser(): User
    {
        return $this->targetUser ??= User::findOrFail($this->route('user'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateClient', $this->targetUser());
    }

    protected function ignoredUserId(): ?int
    {
        return $this->targetUser()->id;
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            // The account's current company stays valid even if it has since
            // been deactivated; a different one must be active.
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')
                    ->where('tenant_id', app('tenant')->id)
                    ->where(fn ($q) => $q->where('operational_status', 'active')
                        ->orWhere('id', $this->targetUser()->company_id)),
            ],
        ];
    }

    /**
     * An already-assigned project stays valid even if it has since been
     * deactivated.
     *
     * @return array<int, int>
     */
    protected function allowedProjectIds(int $companyId): array
    {
        $assigned = $this->targetUser()->projects()->where('projects.company_id', $companyId)->pluck('projects.id')->all();

        return array_values(array_unique(array_merge(parent::allowedProjectIds($companyId), $assigned)));
    }
}
