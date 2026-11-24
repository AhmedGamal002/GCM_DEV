<?php

namespace App\Http\Requests\Trips;

use App\Domain\Trips\Actions\CreateTripByDriverAction;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/driver/trips — the driver's create form (FRD V01.14 §1.14 "from the driver's point of view",
 * part 7.6). The driver is the account; the date is the moment of creation; the PO and the costs are the
 * server's — none of them can be sent. The live location and the live photo are REQUIRED (ج12): the form's
 * camera and GPS widgets are what make them live, and the location link must be the one the widget builds
 * (a Google Maps coordinates link), never free text.
 */
class StoreDriverTripRequest extends FormRequest
{
    use NormalizesRichTextInput;

    /** What gcmMapsLink() builds: https://www.google.com/maps/search/?api=1&query=24.713600,46.675300 */
    private const LOCATION_PATTERN = '#^https://www\.google\.com/maps/search/\?api=1&query=-?\d{1,3}(\.\d+)?,-?\d{1,3}(\.\d+)?$#';

    public function authorize(): bool
    {
        return $this->user()->can('createAsDriver', Trip::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('additional_data')) {
            $this->merge(['additional_data' => $this->normalizeRichText($this->input('additional_data'))]);
        }
    }

    public function rules(): array
    {
        $photo = ['file', 'mimes:jpg,jpeg,png', 'max:'.config('trips.attachment_max_kb')];

        return [
            'company_id' => ['required', 'integer'],
            'project_id' => ['required', 'integer'],
            'vehicle_id' => ['required', 'integer'],
            'main_service_id' => ['required', 'integer'],
            'sub_service_id' => ['nullable', 'integer'],
            'going_asset_id' => ['nullable', 'integer'],
            'returning_asset_id' => ['nullable', 'integer'],
            'location_url' => ['required', 'string', 'max:2048', 'regex:'.self::LOCATION_PATTERN],
            'additional_data' => ['nullable', 'string'],
            'live_photo' => ['required', ...$photo],
            'other_attachment' => ['nullable', 'file', 'mimes:'.implode(',', config('trips.attachment_mimes')), 'max:'.config('trips.attachment_max_kb')],
        ];
    }

    /** @return array<string, mixed> only what the driver's form may send */
    public function tripData(): array
    {
        return array_intersect_key($this->validated(), array_flip(['company_id', 'project_id', ...CreateTripByDriverAction::FIELDS]));
    }
}
