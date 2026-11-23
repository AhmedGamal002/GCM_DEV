<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The client rejects a trip ("Client Issue", FRD §1.14, part 7.5): the
 * reasons in the comment — required (decision ج8) — and an optional file.
 * A sub-service chosen on the page is not sent / not kept (ج7).
 */
class ClientRejectTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ClientTripController authorizes the trip (clientReview)
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:'.implode(',', config('trips.attachment_mimes')), 'max:'.config('trips.attachment_max_kb')],
        ];
    }
}
