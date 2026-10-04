<?php

namespace App\Http\Requests\Services;

use App\Models\SubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubServiceStatusRequest extends FormRequest
{
    private ?SubService $target = null;

    private function target(): SubService
    {
        return $this->target ??= SubService::findOrFail($this->route('service'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->target());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
