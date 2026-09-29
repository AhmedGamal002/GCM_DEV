<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.7.3 "Insert an asset into a project". The company / type
 * dropdowns on the page only narrow the lists — the server needs the
 * project (from the URL) and the asset. Whether they may be paired
 * (active project, available asset) is InsertAssetIntoProjectAction's job.
 */
class InsertAssetIntoProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A project that isn't in this tenant is a 404, not a validation error.
        Project::findOrFail($this->route('project'));

        return $this->user()->can('insertIntoProject', Asset::class);
    }

    public function rules(): array
    {
        return [
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->where('tenant_id', app('tenant')->id),
            ],
        ];
    }
}
