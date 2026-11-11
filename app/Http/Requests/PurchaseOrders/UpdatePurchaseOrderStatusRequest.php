<?php

namespace App\Http\Requests\PurchaseOrders;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    private ?PurchaseOrder $targetOrder = null;

    private function targetOrder(): PurchaseOrder
    {
        return $this->targetOrder ??= PurchaseOrder::findOrFail($this->route('purchaseOrder'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetOrder());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'deactivated'])],
        ];
    }
}
