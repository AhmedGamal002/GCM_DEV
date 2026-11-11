<?php

namespace App\Http\Controllers\Web\PurchaseOrders;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — the list page fetches everything from
 * /api/v1/purchase-orders. The route carries `can:viewAny`, so a role the
 * FRD gives no access to POs (Data Entry, the client Project Auditor,
 * contractor users, drivers) gets a 403 page rather than an empty shell.
 */
class PurchaseOrderListController extends Controller
{
    public function index()
    {
        return view('tenant.purchase-orders.list');
    }
}
