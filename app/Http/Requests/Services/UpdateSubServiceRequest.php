<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\SubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.9.2 edit page: "أسم الخدمة والبيانات الاختيارية" — the name
 * and the optional data (the skip colour code and the additional data),
 * nothing else (UpdateSubServiceAction refuses any edit once the
 * sub-service is locked). The main service, asset type, capacities,
 * facilities and the recycle-receipt flag are fixed at creation: they are
 * not in the validated set, so a payload carrying them is simply ignored.
 * operational_status changes only through the status endpoint.
 *
 * Not route-model-bound (`{service}` is a raw id, see SubServiceController).
 */
class UpdateSubServiceRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?SubService $target = null;

    private function target(): SubService
    {
        return $this->target ??= SubService::findOrFail($this->route('service'));
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
        $sub = $this->target();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')->where('main_service_id', $sub->main_service_id)->ignore($sub->id),
            ],
            'skip_color_code' => ['nullable', Rule::in(array_keys(SubService::SKIP_COLORS))],
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
