<?php

namespace App\Http\Controllers\Web\Trips;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Blade shells of GCM's trip pages (FRD V01.14 §1.14, part 7.4): the list with its counters and filters, the
 * create / edit forms, and the details with the history. The client's own pages are ClientTripController (7.5).
 * Every page reads and writes through /api/v1/trips…; the Gate calls here are the UX guard (a 403 page instead
 * of an empty one), the API's policy and actions are the enforcement.
 *
 *  - list / view   System Admin, Data Entry and the Auditor (view only)
 *  - add / edit    System Admin and Data Entry (`can:create` on the route)
 */
class TripPageController extends Controller
{
    public function list()
    {
        $this->staffOnly();

        return view('tenant.trips.list', ['canManage' => Gate::allows('create', Trip::class)]);
    }

    public function add()
    {
        return view('tenant.trips.add');
    }

    public function view(Request $request, int $trip)
    {
        $this->staffOnly();

        return view('tenant.trips.view', ['trip' => $this->visible($request, $trip), 'canManage' => Gate::allows('create', Trip::class)]);
    }

    public function edit(Request $request, int $trip)
    {
        return view('tenant.trips.edit', ['trip' => $this->visible($request, $trip)]);
    }

    /** GCM's staff only — the client has its own pages (a client account is sent to a 403 here, like the API's staff lists). */
    private function staffOnly(): void
    {
        abort_unless(request()->user()->hasAnyRole(Trip::STAFF_VIEW_ROLES), 403);
    }

    private function visible(Request $request, int $id): Trip
    {
        return Trip::query()->visibleTo($request->user())->findOrFail($id);
    }
}
