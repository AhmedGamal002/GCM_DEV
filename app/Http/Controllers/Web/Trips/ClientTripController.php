<?php

namespace App\Http\Controllers\Web\Trips;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * Blade shells of the client's trip pages (FRD V01.14 §1.14 "مراجعة العميل
 * للرحلات", part 7.5) — the client's project manager / auditor only (GCM's
 * own trip pages are separate, part 7.4). Every page fetches its data from
 * /api/v1/trips…; a trip of a project the account doesn't see is a 404 here
 * already, like on the API (Trip::scopeVisibleTo).
 *
 *  - list    the trips of their projects, the four counters, filters, export
 *  - view    the details (FRD "العميل" details page) with the approve button
 *  - review  the approve / reject page: sub-service, signature, stamp, attachment, comment
 */
class ClientTripController extends Controller
{
    public function list(Request $request)
    {
        $this->clientOnly($request);

        return view('tenant.trips.client.list');
    }

    public function view(Request $request, int $trip)
    {
        $this->clientOnly($request);

        return view('tenant.trips.client.view', ['trip' => $this->visible($request, $trip)]);
    }

    public function review(Request $request, int $trip)
    {
        $this->clientOnly($request);

        return view('tenant.trips.client.review', ['trip' => $this->visible($request, $trip)]);
    }

    private function clientOnly(Request $request): void
    {
        abort_unless($request->user()->isClient(), 403);
    }

    private function visible(Request $request, int $id): Trip
    {
        return Trip::query()->visibleTo($request->user())->findOrFail($id);
    }
}
