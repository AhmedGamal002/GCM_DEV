<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Http\Requests\Services\Concerns\ValidatesSubServiceComponents;
use App\Models\MainService;
use App\Models\SubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.9.2 "Create new sub-service" form.
 */
class StoreSubServiceRequest extends FormRequest
{
    use NormalizesRichTextInput, ValidatesSubServiceComponents;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', SubService::class);
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')->where('main_service_id', (int) $this->input('main_service_id')),
            ],
            'main_service_id' => ['required', 'integer', Rule::exists('main_services', 'id')->where('tenant_id', $tenantId)],
            'asset_type' => ['required', Rule::in(SubService::ASSET_TYPES)],
            'capacity_ids' => ['required', 'array', 'min:1'],
            'capacity_ids.*' => ['integer', 'distinct', Rule::exists('asset_capacity_categories', 'id')->where('tenant_id', $tenantId)],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'distinct', Rule::exists('intermediate_facilities', 'id')->where('tenant_id', $tenantId)],
            'requires_recycle_receipt' => ['sometimes', 'boolean'],
            'skip_color_code' => ['nullable', Rule::in(array_keys(SubService::SKIP_COLORS))],
            'operational_status' => ['required', Rule::in(['active', 'deactivated'])],
            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['main_service_id', 'asset_type', 'capacity_ids', 'capacity_ids.*', 'facility_ids.*'])) {
                return;
            }

            $main = MainService::find((int) $this->input('main_service_id'));

            // A deactivated main service isn't available for new sub-services.
            if (! $main->isActive()) {
                $validator->errors()->add('main_service_id', __('The selected main service is deactivated.'));

                return;
            }

            $this->checkCapacitiesFitType($validator, $this->input('asset_type'), $this->input('capacity_ids'));
            $this->checkFacilities($validator, $main, $this->input('facility_ids'));
        });
    }
}
