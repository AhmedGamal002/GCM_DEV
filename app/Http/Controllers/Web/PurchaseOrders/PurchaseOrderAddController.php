<?php

namespace App\Http\Controllers\Web\PurchaseOrders;

use App\Http\Controllers\Controller;

class PurchaseOrderAddController extends Controller
{
    public function index()
    {
        return view('tenant.purchase-orders.add');
    }
}
