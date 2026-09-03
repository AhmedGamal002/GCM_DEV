<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shells only for the asset capacity categories management pages
 * (FRD §1.7.2 sidebar item "التصنيفات"). List / create / edit — there is
 * no separate details page; the list's detail link goes straight to
 * edit. Data via /api/v1/asset-capacity-categories.
 */
class AssetCategoryController extends Controller
{
    public function list()
    {
        return view('tenant.asset-categories.list');
    }

    public function add()
    {
        return view('tenant.asset-categories.add');
    }

    public function edit(int $category)
    {
        return view('tenant.asset-categories.edit', ['categoryId' => $category]);
    }
}
