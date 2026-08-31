<?php

namespace App\Http\Controllers\Web\Drivers;

use App\Http\Controllers\Controller;

/**
 * Blade shells only — same pattern as UserAddController/UserAccountController.
 * All actual data goes through /api/v1/drivers client-side; authorization
 * happens entirely through those API calls, not here.
 */
class DriverAccountController extends Controller
{
    public function list()
    {
        return view('tenant.drivers.list');
    }

    public function add()
    {
        return view('tenant.drivers.add');
    }

    public function view(int $driver)
    {
        return view('tenant.drivers.view', ['driverId' => $driver]);
    }

    public function edit(int $driver)
    {
        return view('tenant.drivers.edit', ['driverId' => $driver]);
    }
}
