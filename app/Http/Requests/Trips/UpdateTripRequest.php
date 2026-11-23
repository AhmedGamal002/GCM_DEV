<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/v1/trips/{trip} (multipart: POST with `_method=PATCH`) — GCM's
 * edit (completing a driver's trip, corrections; ج9: the status stays). Only
 * the keys sent change. The company is locked (the trip number is its).
 * Also the corrections that may go with accepting the client's objection.
 * Who may and from which status: the controller (TripPolicy) and the action.
 */
class UpdateTripRequest extends FormRequest
{
    use TripRequestRules;

    public function authorize(): bool
    {
        return true; // the trip is looked up after the tenant middleware — TripController authorizes
    }

    public function rules(): array
    {
        return [...$this->dataRules(false), ...$this->fileRules()];
    }
}
