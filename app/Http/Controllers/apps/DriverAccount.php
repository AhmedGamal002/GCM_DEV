<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

/**
 * Blade shells only — same pattern as UserAdd/UserAccount. All actual
 * data goes through /api/v1/drivers client-side; authorization happens
 * entirely through those API calls, not here.
 */
class DriverAccount extends Controller
{
    public function list()
    {
        return view('content.apps.app-driver-list');
    }

    public function add()
    {
        return view('content.apps.app-driver-add');
    }

    public function view(int $driver)
    {
        return view('content.apps.app-driver-view', ['driverId' => $driver]);
    }

    public function edit(int $driver)
    {
        return view('content.apps.app-driver-edit', ['driverId' => $driver]);
    }
}
