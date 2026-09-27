<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD §1.7.3 asset edit page: the name is the ONLY editable field
 * ("اما السعة والتصنيف وما سواها" — everything else is locked). Anything
 * else in the payload is simply ignored (not in the validated set).
 * operational_status changes only through the status endpoint.
 *
 * Not route-model-bound (the `{asset}` param is a raw id, see
 * AssetController's docblock).
 */
class UpdateAssetRequest extends FormRequest
{
    private ?Asset $targetAsset = null;

    private function targetAsset(): Asset
    {
        return $this->targetAsset ??= Asset::findOrFail($this->route('asset'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetAsset());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
