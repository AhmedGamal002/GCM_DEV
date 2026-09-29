<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shells only for the asset capacity categories management pages
 * (FRD §1.7.2 sidebar item "التصنيفات"). List / create / view / edit.
 * Data via /api/v1/asset-capacity-categories. The {category} param isn't
 * route-model-bound (same SubstituteBindings-before-tenant reason as
 * AssetController), so authorization happens through the API call, not
 * here.
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

    public function view(int $category)
    {
        return view('tenant.asset-categories.view', ['categoryId' => $category]);
    }

    public function edit(int $category)
    {
        return view('tenant.asset-categories.edit', ['categoryId' => $category]);
    }
}
