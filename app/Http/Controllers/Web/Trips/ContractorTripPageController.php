<?php

namespace App\Http\Controllers\Web\Trips;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * Blade shells of the contractor user's trip pages (FRD V01.14 §1.14 "من منظور دور مستخدم المتعهد", part 7.7) — view
 * and export only: the trips whose driver or vehicle belongs to his contractor (ج19), with what a field-side user may
 * see (ج18: no costs, no PO number, no history) and the delivery note once the trip is completed. GCM's pages are
 * /app/trip/*, the client's /app/client-trip/*, the driver's /app/driver-trip/*. Everything is read through
 * /api/v1/trips; a trip that isn't his is a 404 here like on the API (Trip::scopeVisibleTo).
 */
class ContractorTripPageController extends Controller
{
    public function list(Request $request)
    {
        $this->contractorUserOnly($request);

        return view('tenant.trips.contractor.list');
    }

    public function view(Request $request, int $trip)
    {
        $this->contractorUserOnly($request);

        return view('tenant.trips.contractor.view', ['trip' => Trip::query()->visibleTo($request->user())->findOrFail($trip)]);
    }

    private function contractorUserOnly(Request $request): void
    {
        abort_unless($request->user()->isContractorUser(), 403);
    }
}
