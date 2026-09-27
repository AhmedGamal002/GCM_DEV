<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shells only, per the FRD's dedicated "asset details" and "edit
 * asset" pages. Both fetch/save against /api/v1/assets/{id}; the {asset}
 * param isn't route-model-bound (same SubstituteBindings-before-tenant
 * reason as AssetController), so authorization happens through the API
 * call, not here.
 */
class AssetAccountController extends Controller
{
    public function view(int $asset)
    {
        return view('tenant.assets.view', ['assetId' => $asset]);
    }

    public function edit(int $asset)
    {
        return view('tenant.assets.edit', ['assetId' => $asset]);
    }
}
