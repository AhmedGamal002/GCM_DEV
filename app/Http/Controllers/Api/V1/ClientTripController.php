<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Trips\Actions\ClientApproveTripAction;
use App\Domain\Trips\Actions\ClientRejectTripAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\ClientApproveTripRequest;
use App\Http\Requests\Trips\ClientRejectTripRequest;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The client's side of the trips (FRD V01.14 §1.14 "مراجعة العميل للرحلات",
 * part 7.5) — what the client's project manager / auditor does besides the
 * list, counters, export and details they share with TripController:
 *
 *  - GET  /trips/client-filters                    the list's filters: their projects and the services of their trips
 *  - GET  /trips/{trip}/client-sub-services        the sub-services they may switch the trip to
 *  - POST /trips/{trip}/client-approve   (otp)     approve — sub-service, signature, stamp, attachment, comment
 *  - POST /trips/{trip}/client-reject    (otp)     reject — the reasons (required) and an attachment
 *
 * A trip of a project they don't see is a 404 (TripController::find). Who may
 * approve is ClientApproverResolver (ج6, the `clientReview` ability); the
 * status, under the row lock, is the action's (409 when it moved on).
 */
class ClientTripController extends Controller
{
    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isClient(), 403);

        $trips = Trip::query()->visibleTo($user);
        $named = fn ($query) => $query->orderBy('name')->get(['id', 'name'])->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values();

        return response()->json(['data' => [
            'projects' => $named(Project::query()->visibleTo($user)),
            'main_services' => $named(MainService::query()->whereIn('id', (clone $trips)->select('trips.main_service_id'))),
            'sub_services' => $named(SubService::query()->whereIn('id', (clone $trips)->whereNotNull('trips.sub_service_id')->select('trips.sub_service_id'))),
        ]]);
    }

    public function subServices(Request $request, int $trip): JsonResponse
    {
        $model = $this->reviewable($request, $trip);

        $subs = ClientApproveTripAction::choices($model)->orderBy('sub_services.name')->get(['sub_services.id', 'sub_services.name']);

        return response()->json(['data' => $subs->map(fn (SubService $s) => ['id' => $s->id, 'name' => $s->name])->values()]);
    }

    public function approve(ClientApproveTripRequest $request, int $trip, ClientApproveTripAction $action)
    {
        $model = $this->reviewable($request, $trip);

        $action->execute($model, $request->user(), $request->approval());

        return app(TripController::class)->show($request, $model->id);
    }

    public function reject(ClientRejectTripRequest $request, int $trip, ClientRejectTripAction $action)
    {
        $model = $this->reviewable($request, $trip);

        $action->execute($model, $request->user(), $request->validated('reason'), $request->file('attachment'));

        return app(TripController::class)->show($request, $model->id);
    }

    /** A trip of a project the client sees (else 404) that the account may approve or reject (else 403). */
    private function reviewable(Request $request, int $id): Trip
    {
        $trip = TripController::find($request, $id);
        Gate::authorize('clientReview', $trip);

        return $trip;
    }
}
