<?php

namespace App\Http\Requests\Concerns;

use App\Models\Contractor;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The "affiliation" radio shared by the driver, vehicle and asset forms
 * (FRD V01.14 §1.3 / §1.5 / §1.7: belongs to GCM, or to a contractor — and
 * choosing a contractor shows the list of contractors to pick one from).
 *
 * `contractor_id` is required exactly when the contractor radio is chosen
 * and must be one of this tenant's contractors (Contractor is tenant-scoped,
 * so another tenant's contractor simply isn't found). A deactivated
 * contractor can't be picked for new work — except the one the record
 * already belongs to, so editing an old vehicle never fails just because
 * its contractor was deactivated since.
 */
trait ValidatesAffiliation
{
    /**
     * @param  int|null  $currentContractorId  the contractor the record already belongs to (edit only)
     * @param  bool  $optional  edit requests: an absent `affiliation` leaves the record's affiliation untouched
     * @return array<string, array<int, mixed>>
     */
    protected function affiliationRules(?int $currentContractorId = null, bool $optional = false): array
    {
        return [
            'affiliation' => [$optional ? 'sometimes' : 'required', Rule::in(['gcm', 'contractor'])],
            'contractor_id' => [
                'nullable',
                'required_if:affiliation,contractor',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($currentContractorId) {
                    if ($this->input('affiliation') !== 'contractor' || $value === null) {
                        return;
                    }

                    $contractor = Contractor::find($value);

                    if (! $contractor) {
                        $fail(__('Choose one of the contractors in the list.'));
                    } elseif (! $contractor->isActive() && (int) $value !== $currentContractorId) {
                        $fail(__('This contractor is deactivated — choose an active contractor.'));
                    }
                },
            ],
        ];
    }
}
