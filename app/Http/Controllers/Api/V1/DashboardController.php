<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\DashboardStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's dashboard (FRD V01.14 "لوحة التحكم", part 8.3): what he may see of the trips, the resources,
 * the purchase orders, and his own latest notifications — see DashboardStats for who gets which section. Read only,
 * any signed-in tenant user, no Policy of its own: every figure is computed through the scopes of the pages it
 * summarises.
 */
class DashboardController extends Controller
{
    public function show(Request $request, DashboardStats $stats): JsonResponse
    {
        return response()->json(['data' => $stats->for($request->user(), $request)]);
    }
}
