<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Domain\PurchaseOrders\Support\PurchaseOrderRules;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD V01.14 §1.13 "انشاء تعاقد جديد". The field vocabulary is
 * PurchaseOrderRules::store(); everything that needs other rows (company and
 * project active and together, the number free, the overlap rule) is checked
 * by CreatePurchaseOrderAction, so it holds for any caller.
 */
class StorePurchaseOrderRequest extends FormRequest
{
    use NormalizesRichTextInput;

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }

    public function rules(): array
    {
        return PurchaseOrderRules::store();
    }
}
