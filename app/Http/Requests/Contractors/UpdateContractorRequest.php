<?php

namespace App\Http\Requests\Contractors;

use App\Models\Contractor;

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

        return $rules;
    }
}
