<?php

namespace App\Http\Requests\Trips;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /api/v1/trips — the staff "create trip" form (FRD §1.14, System
 * Admin / Data Entry). `requires_client_approval = false` creates the trip
 * completed directly (no OTP — ج15), so it carries the completing step's
 * signatures too (د4 / د5).
 */
class StoreTripRequest extends FormRequest
{
    use TripRequestRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Trip::class);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'],
            'requires_client_approval' => ['sometimes', 'boolean'],
            ...$this->dataRules(true),
            ...$this->fileRules(),
            ...$this->completionRules(),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $v) => $this->requireFrdFields($v, true)];
    }
}
