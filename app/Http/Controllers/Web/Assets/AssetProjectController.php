<?php

namespace App\Http\Controllers\Web\Assets;

use App\Http\Controllers\Controller;

/**
 * Blade shell for FRD V01.14 §1.7.3 "Insert an asset into a project". The
 * page loads companies / projects / assets from the API and posts to
 * /api/v1/projects/{project}/assets. The button that opens it only shows
 * for roles that may create assets (System Admin / Data Entry).
 */
class AssetProjectController extends Controller
{
    public function create()
    {
        return view('tenant.assets.insert-into-project');
    }
}
