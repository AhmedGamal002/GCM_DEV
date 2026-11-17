<?php

namespace App\Domain\Trips\Exceptions;

use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A trip action was asked from a status it doesn't start from — approving a
 * trip that was already approved, editing a cancelled one, cancelling it
 * twice, a driver touching a completed trip… Every transition action checks
 * its source status on the locked row and throws this. Rendered as 409 with
 * a readable message (and `code` = trip_transition_not_allowed).
 */
class TripTransitionNotAllowedException extends RuntimeException
{
    public function __construct(
        public readonly string $action,
        public readonly string $status,
    ) {
        parent::__construct("The trip action [{$action}] is not allowed while the trip is [{$status}].");
    }

    public static function for(string $action, Trip $trip): self
    {
        return new self($action, $trip->status);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __('This action is not available for the trip in its current status.'),
            'code' => 'trip_transition_not_allowed',
            'status' => $this->status,
        ], 409);
    }
}
