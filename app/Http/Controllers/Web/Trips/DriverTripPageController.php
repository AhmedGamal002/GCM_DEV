<?php

namespace App\Http\Controllers\Web\Trips;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * Blade shells of the driver's trip pages (FRD V01.14 §1.14 "من منظور دور السائق", part 7.6) — a driver account
 * only, mobile first: his trips table, the create form (live location + live photo, ج12) and the details with the
 * recycle receipt photo. GCM's staff pages are TripPageController (7.4), the client's ClientTripController (7.5).
 * Every page reads and writes through /api/v1/trips and /api/v1/driver/trips; a trip that isn't his is a 404 here
 * like on the API (Trip::scopeVisibleTo).
 */
class DriverTripPageController extends Controller
{
    public function list(Request $request)
    {
        $this->driverOnly($request);

        return view('tenant.trips.driver.list');
    }

    public function add(Request $request)
    {
        $this->driverOnly($request);

        return view('tenant.trips.driver.add');
    }

    public function view(Request $request, int $trip)
    {
        $this->driverOnly($request);

        return view('tenant.trips.driver.view', ['trip' => Trip::query()->visibleTo($request->user())->findOrFail($trip)]);
    }

    private function driverOnly(Request $request): void
    {
        abort_unless($request->user()->hasRole('driver') && $request->user()->driver !== null, 403);
    }
}
