<?php

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\MainService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD V01.14 §1.9.1 edit page: "أسم الخدمة والبيانات الاختيارية" — the name
 * and the additional data, nothing else (UpdateMainServiceAction refuses
 * any edit once a PO includes the service). The type of service and the
 * supported vehicle categories are fixed at creation: they are not in the
 * validated set, so a payload carrying them is simply ignored.
 * operational_status changes only through the status endpoint.
 *
 * Not route-model-bound (`{service}` is a raw id, see MainServiceController).
 */
class UpdateMainServiceRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?MainService $target = null;

    private function target(): MainService
    {
        return $this->target ??= MainService::findOrFail($this->route('service'));
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

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('main_services', 'name')->where('tenant_id', $tenantId)->ignore($this->target()->id),
            ],
            'additional_data' => ['nullable', 'string'],
        ];
    }
}
