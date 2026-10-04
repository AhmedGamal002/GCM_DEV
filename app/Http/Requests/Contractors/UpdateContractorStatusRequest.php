<?php

namespace App\Http\Requests\Contractors;

use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractorStatusRequest extends FormRequest
{
    private ?Contractor $targetContractor = null;

    private function targetContractor(): Contractor
    {
        return $this->targetContractor ??= Contractor::findOrFail($this->route('contractor'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetContractor());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
