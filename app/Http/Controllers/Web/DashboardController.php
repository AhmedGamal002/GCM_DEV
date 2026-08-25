<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * Blade shell only — no real data. Just proves the session/tenant/role
 * context is wired correctly. Real dashboard content (per-role stats)
 * lands in Week 8 (GET /api/v1/dashboard).
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }
}
