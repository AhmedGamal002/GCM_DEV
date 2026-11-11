<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Domain\PurchaseOrders\Support\PurchaseOrderRules;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FRD V01.14 §1.13 "صفحة تعديل تعاقد". The shape is PurchaseOrderRules::update()
 * (every top-level field optional, a line sent is complete). What may actually
 * change is PurchaseOrderEditability's call — decision ب2: everything until a
 * trip is recorded on the PO, only the quota after — and UpdatePurchaseOrderAction
 * enforces it (422 naming the locked field), so this request doesn't repeat it.
 */
class UpdatePurchaseOrderRequest extends FormRequest
{
    use NormalizesRichTextInput;

    private ?PurchaseOrder $targetOrder = null;

    private function targetOrder(): PurchaseOrder
    {
        return $this->targetOrder ??= PurchaseOrder::findOrFail($this->route('purchaseOrder'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetOrder());
    }

    public function rules(): array
    {
        return PurchaseOrderRules::update();
    }
}
