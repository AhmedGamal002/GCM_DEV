<?php

namespace App\Http\Requests\Services;

use App\Models\MainService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMainServiceStatusRequest extends FormRequest
{
    private ?MainService $target = null;

    private function target(): MainService
    {
        return $this->target ??= MainService::findOrFail($this->route('service'));
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
