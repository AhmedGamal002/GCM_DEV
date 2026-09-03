<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetStatusRequest extends FormRequest
{
    private ?Asset $targetAsset = null;

    private function targetAsset(): Asset
    {
        return $this->targetAsset ??= Asset::findOrFail($this->route('asset'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetAsset());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'on_maintenance', 'deactivated'])],
        ];
    }
}
