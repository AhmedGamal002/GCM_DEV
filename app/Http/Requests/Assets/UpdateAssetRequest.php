<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD §1.7.3 asset edit page: only the name is editable per the FRD
 * ("اما السعة والتصنيف وما سواها" — everything else is locked); the client
 * also asked for the additional data (free notes — no effect on contracts or
 * trips, so nothing to protect) to stay editable. Anything else in the
 * payload is simply ignored (not in the validated set).
 * operational_status changes only through the status endpoint.
 *
 * Not route-model-bound (the `{asset}` param is a raw id, see
 * AssetController's docblock).
 */
class UpdateAssetRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?Asset $targetAsset = null;

    private function targetAsset(): Asset
    {
        return $this->targetAsset ??= Asset::findOrFail($this->route('asset'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetAsset());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
