<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Http\Requests\Services\Concerns\ValidatesSubServiceComponents;
use App\Models\SubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.9.2 edit page: name + optional data, plus the asset type,
 * capacities, facilities, recycle-receipt flag and skip colour while the
 * sub-service is unlocked (UpdateSubServiceAction refuses any edit once it
 * is locked). The main service is never accepted (locked);
 * operational_status changes only through the status endpoint.
 *
 * Not route-model-bound (`{service}` is a raw id, see SubServiceController).
 */
class UpdateSubServiceRequest extends FormRequest
{
    use NormalizesRichTextInput, ValidatesSubServiceComponents;

    private ?SubService $target = null;

    private function target(): SubService
    {
        return $this->target ??= SubService::with(['mainService', 'capacities', 'facilities'])->findOrFail($this->route('service'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    public function rules(): array
    {
        $tenantId = app('tenant')->id;
        $sub = $this->target();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')->where('main_service_id', $sub->main_service_id)->ignore($sub->id),
            ],
            'asset_type' => ['sometimes', Rule::in(SubService::ASSET_TYPES)],
            'capacity_ids' => ['sometimes', 'array', 'min:1'],
            'capacity_ids.*' => ['integer', 'distinct', Rule::exists('asset_capacity_categories', 'id')->where('tenant_id', $tenantId)],
            'facility_ids' => ['sometimes', 'nullable', 'array'],
            'facility_ids.*' => ['integer', 'distinct', Rule::exists('intermediate_facilities', 'id')->where('tenant_id', $tenantId)],
            'requires_recycle_receipt' => ['sometimes', 'boolean'],
            'skip_color_code' => ['nullable', Rule::in(array_keys(SubService::SKIP_COLORS))],
            'additional_data' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['asset_type', 'capacity_ids', 'capacity_ids.*', 'facility_ids.*'])) {
                return;
            }

            $sub = $this->target();

            // A field the form doesn't send keeps its stored value.
            if ($this->has('asset_type') || $this->has('capacity_ids')) {
                $this->checkCapacitiesFitType(
                    $validator,
                    $this->input('asset_type', $sub->asset_type),
                    $this->input('capacity_ids', $sub->capacities->pluck('id')->all()),
                );
            }

            if ($this->has('facility_ids')) {
                $this->checkFacilities($validator, $sub->mainService, $this->input('facility_ids'), $sub->facilities->pluck('id')->all());
            }
        });
    }
}
