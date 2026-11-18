<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Trips\Actions\RemindClientAction;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * POST /api/v1/trips/{trip}/remind-client — the "remind the client to
 * approve" button of a trip's details page (FRD V01.14, decision ج17, part
 * 7.3). Who may press it: GCM staff who manage trips (System Admin, Data
 * Entry) — the FRD shows the button on the staff's trip page only. When the
 * trips' TripPolicy lands (7.4) this check moves there; the rules of the
 * reminder itself are in RemindClientAction.
 *
 * {trip} is not route-model-bound (SubstituteBindings runs before the tenant
 * middleware), so it is looked up here, after it — a trip of another tenant
 * is a 404.
 */
class TripReminderController extends Controller
{
    public function store(Request $request, int $trip, RemindClientAction $action)
    {
        abort_unless($request->user()->hasAnyRole(['system_admin', 'data_entry']), 403);

        $result = $action->execute(Trip::findOrFail($trip), $request->user());

        return response()->json(['data' => [
            'sent_to' => $result['sent_to'],
            'next_reminder_at' => $result['next_reminder_at']->toIso8601String(),
        ]]);
    }
}
