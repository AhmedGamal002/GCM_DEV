<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The completing steps — the final approval and rejecting the client's
 * objection (both behind the OTP, ج15): the signatures (د4 / د5) plus
 * optional last corrections of the trip's data and files (ج7: e.g. the
 * facility GCM must choose before the final approval).
 */
class CompleteTripRequest extends FormRequest
{
    use TripRequestRules;

    public function authorize(): bool
    {
        return true; // TripController authorizes the trip
    }

    public function rules(): array
    {
        return [...$this->dataRules(false), ...$this->fileRules(), ...$this->completionRules()];
    }
}
