<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The client approves a trip (FRD §1.14 "تعديل بيانات الرحلة" from the
 * client's side, part 7.5). The page sends only what the FRD form holds:
 * the sub-service (the one trip field the client may change), the signature
 * (drawn now, or the saved one), an optional stamp, an attachment and a
 * comment. Anything else in the request (cost, quantity, project, status…)
 * is not read — input() only returns these keys.
 */
class ClientApproveTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ClientTripController authorizes the trip (clientReview)
    }

    protected function prepareForValidation(): void
    {
        foreach (['use_saved_signature', 'save_signature'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $this->merge([$key => filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->input($key)]);
            }
        }
    }

    public function rules(): array
    {
        $image = ['nullable', 'file', 'mimes:'.implode(',', config('trips.signature_mimes')), 'max:'.config('trips.signature_max_kb')];

        return [
            'sub_service_id' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:'.implode(',', config('trips.attachment_mimes')), 'max:'.config('trips.attachment_max_kb')],
            'signature' => $image,
            'stamp' => $image,
            'use_saved_signature' => ['sometimes', 'boolean'],
            'save_signature' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> the input of ClientApproveTripAction */
    public function approval(): array
    {
        return array_filter([
            'sub_service_id' => $this->validated('sub_service_id'),
            'comment' => $this->validated('comment'),
            'attachment' => $this->file('attachment'),
            'signature' => $this->file('signature'),
            'stamp' => $this->file('stamp'),
            'use_saved_signature' => (bool) $this->validated('use_saved_signature', false),
            'save_signature' => (bool) $this->validated('save_signature', false),
        ], fn ($v) => $v !== null);
    }
}
