<?php

namespace App\Http\Controllers\Web\PurchaseOrders;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's "تفاصيل تعاقد" and "تعديل تعاقد" pages. Both
 * fetch / save against /api/v1/purchase-orders/{id}; the {purchaseOrder}
 * param isn't route-model-bound (the same SubstituteBindings-before-tenant
 * reason as ProjectController), so a PO the user may not see is refused by
 * the API call (403 / 404), not here.
 */
class PurchaseOrderAccountController extends Controller
{
    public function view(int $purchaseOrder)
    {
        return view('tenant.purchase-orders.view', ['purchaseOrderId' => $purchaseOrder]);
    }

    public function edit(int $purchaseOrder)
    {
        return view('tenant.purchase-orders.edit', ['purchaseOrderId' => $purchaseOrder]);
    }
}
