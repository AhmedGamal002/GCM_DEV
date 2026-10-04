<?php

namespace App\Http\Requests\Contractors;

use App\Models\Contractor;
use App\Models\User;
use Closure;

/**
 * FRD V01.14 §1.10.4: every contractor field is editable by (System Admin /
 * Data Entry) EXCEPT the short name — the contractor's ID is built from it
 * (prefix + C + number), so it is locked after creation. A `prefix` in the
 * payload is simply not validated or saved. Deactivation has its own endpoint
 * (UpdateContractorStatusRequest).
 *
 * Not route-model-bound (the `{contractor}` param is a raw id — see
 * ContractorController's docblock).
 */
class UpdateContractorRequest extends StoreContractorRequest
{
    private ?Contractor $targetContractor = null;

    private function targetContractor(): Contractor
    {
        return $this->targetContractor ??= Contractor::findOrFail($this->route('contractor'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetContractor());
    }

    public function rules(): array
    {
        $rules = $this->baseRules(null);
        unset($rules['prefix']);

        // FRD §1.10.2: "حساب ممثل المتعهد" — optional; one of THIS contractor's
        // users (a contractor user of another contractor, drivers and GCM staff
        // are refused). An unchanged value is always accepted so saving other
        // fields still works after the rep was later put on vacation /
        // deactivated.
        $rules['representative_id'] = ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
            $contractor = $this->targetContractor();

            if ((int) $value === $contractor->representative_id) {
                return;
            }

            $user = User::find($value);

            if (! $user || $user->contractor_id !== $contractor->id || ! $user->isContractorUser() || $user->status !== 'active') {
                $fail(__('Choose an active user of this contractor.'));
            }
        }];

        return $rules;
    }
}
