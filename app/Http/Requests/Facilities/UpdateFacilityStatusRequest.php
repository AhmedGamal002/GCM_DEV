<?php

namespace App\Http\Requests\Facilities;

use App\Models\IntermediateFacility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacilityStatusRequest extends FormRequest
{
    private ?IntermediateFacility $targetFacility = null;

    private function targetFacility(): IntermediateFacility
    {
        return $this->targetFacility ??= IntermediateFacility::findOrFail($this->route('facility'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetFacility());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
