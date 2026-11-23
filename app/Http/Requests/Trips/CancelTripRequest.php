<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

/** POST /api/v1/trips/{trip}/cancel — the reason is optional (ج2). */
class CancelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TripController authorizes the trip
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
