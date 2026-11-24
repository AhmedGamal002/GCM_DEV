<?php

namespace App\Http\Requests\Trips;

use App\Domain\Trips\Support\TripInput;
use App\Http\Requests\Concerns\NormalizesRichTextInput;
use App\Models\MainService;
use Illuminate\Validation\Validator;

/**
 * The shapes the trip endpoints accept (part 7.4). These rules check TYPES
 * and the FRD's required fields only; whether the choices hang together and
 * are available (same company, PO of the project, active, compatible, the
 * quantity within the capacity…) is the actions' job — TripInput and
 * TripChoiceCheck — so the same answer comes whatever the caller.
 *
 * Multipart forms send booleans as "true" / "false" / "1" / "0": they are
 * turned into real booleans first. `additional_data` goes through the
 * rich-text normaliser (the model's cast sanitises it again).
 */
trait TripRequestRules
{
    use NormalizesRichTextInput;

    /** @var list<string> */
    protected array $booleans = ['requires_client_approval', 'requires_purchase_order', 'use_saved_gcm_signature', 'save_gcm_signature'];

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach ($this->booleans as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $value = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $merge[$key] = $value ?? $this->input($key);
            }
        }

        if ($this->has('additional_data')) {
            $merge['additional_data'] = $this->normalizeRichText($this->input('additional_data'));
        }

        $this->merge($merge);
    }

    /** The trip's data fields; `$presence` is 'required' on create and 'sometimes' on edit. */
    protected function dataRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'trip_at' => [$req, 'date'],
            'project_id' => [$req, 'integer'],
            'driver_id' => [$req, 'integer'],
            'vehicle_id' => [$req, 'integer'],
            'going_asset_id' => ['nullable', 'integer'],
            'returning_asset_id' => ['nullable', 'integer'],
            'main_service_id' => [$req, 'integer'],
            // FRD staff form: the sub-service is "لازم" (the driver's form leaves it optional — 7.6).
            'sub_service_id' => $creating ? ['required', 'integer'] : ['sometimes', 'nullable', 'integer'],
            'intermediate_facility_id' => ['nullable', 'integer'],
            'quantity_ton' => ['nullable', 'numeric', 'min:0'],
            'quantity_cbm' => ['nullable', 'numeric', 'min:0'],
            'requires_purchase_order' => ['sometimes', 'boolean'],
            'purchase_order_id' => ['nullable', 'integer'],
            'trip_cost' => ['nullable', 'numeric', 'min:0'],
            'ancillary_cost' => ['nullable', 'numeric', 'min:0'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'waste_manifest_number' => ['nullable', 'string', 'max:100'],
            'recycle_receipt_number' => ['nullable', 'string', 'max:100'],
            'location_url' => ['nullable', 'string', 'max:2048'],
            'additional_data' => ['nullable', 'string'],
            'client_approver_id' => ['nullable', 'integer'],
            'gcm_approver_id' => ['nullable', 'integer'],
        ];
    }

    /** The uploads of the staff form (TripInput::FILES). Same limits as TripFiles, checked early. */
    protected function fileRules(): array
    {
        $document = ['nullable', 'file', 'mimes:'.implode(',', config('trips.attachment_mimes')), 'max:'.config('trips.attachment_max_kb')];

        return [
            'delivery_note' => $document,
            'waste_manifest' => $document,
            // A photo (FRD "صورة إيصال التدوير"): it is printed as a page of the trip's combined PDF (8.1).
            'recycle_receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('trips.attachment_max_kb')],
            'other_attachment' => $document,
            'live_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('trips.attachment_max_kb')],
        ];
    }

    /**
     * The completing step's signatures (TripSignatures::forCompletion): the client's uploaded by staff when
     * the trip has none (د4) and GCM's representative's — saved or drawn now (د5).
     */
    protected function completionRules(): array
    {
        $image = ['nullable', 'file', 'mimes:'.implode(',', config('trips.signature_mimes')), 'max:'.config('trips.signature_max_kb')];

        return [
            'client_signature' => $image,
            'client_stamp' => $image,
            'client_signer_name' => ['nullable', 'string', 'max:255'],
            'gcm_signature' => $image,
            'use_saved_gcm_signature' => ['sometimes', 'boolean'],
            'save_gcm_signature' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * FRD staff form: the quantity and the facility are "لازم" — except for a supplying service (no waste,
     * ج11; facility optional per §1.9.2); the trip cost is "لازم" when the trip has no PO.
     */
    protected function requireFrdFields(Validator $validator, bool $creating): void
    {
        $mainId = $this->input('main_service_id');
        $supplying = is_numeric($mainId) && MainService::whereKey((int) $mainId)->value('service_type') === 'supplying';

        if (! $creating) {
            return;
        }

        if (! $supplying && blank($this->input('quantity_ton')) && blank($this->input('quantity_cbm'))) {
            $validator->errors()->add('quantity_ton', __('validation.required', ['attribute' => __('validation.attributes.quantity_ton')]));
        }

        if (! $supplying && blank($this->input('intermediate_facility_id'))) {
            $validator->errors()->add('intermediate_facility_id', __('validation.required', ['attribute' => __('validation.attributes.intermediate_facility_id')]));
        }

        if ($this->input('requires_purchase_order') === false && blank($this->input('trip_cost'))) {
            $validator->errors()->add('trip_cost', __('validation.required', ['attribute' => __('validation.attributes.trip_cost')]));
        }
    }

    /** @return array<string, mixed> the trip data for the action (only the keys sent) */
    public function tripData(): array
    {
        $keys = [...TripInput::DATA, 'company_id', 'project_id', 'requires_client_approval', 'requires_purchase_order', 'purchase_order_id', 'trip_cost', 'ancillary_cost'];

        return array_intersect_key($this->validated(), array_flip($keys));
    }

    /** @return array<string, ?\Illuminate\Http\UploadedFile> */
    public function tripFiles(): array
    {
        return array_filter(array_map(fn ($key) => $this->file($key), array_combine(array_keys(TripInput::FILES), array_keys(TripInput::FILES))));
    }

    /** @return array<string, mixed> the completion input (TripSignatures::forCompletion) */
    public function completion(): array
    {
        return array_filter([
            'client_signature' => $this->file('client_signature'),
            'client_stamp' => $this->file('client_stamp'),
            'client_signer_name' => $this->input('client_signer_name'),
            'gcm_signature' => $this->file('gcm_signature'),
            'use_saved_gcm_signature' => (bool) $this->input('use_saved_gcm_signature', false),
            'save_gcm_signature' => (bool) $this->input('save_gcm_signature', false),
        ], fn ($v) => $v !== null);
    }
}
