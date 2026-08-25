<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('platform.dashboard');
    }
}
