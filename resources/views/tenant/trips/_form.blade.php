{{-- The staff trip form (FRD V01.14 §1.14 "إنشاء رحلة جديدة" / "تعديل بيانات الرحلة"), shared by add and edit. $mode = 'add' | 'edit'.
     Every list is filled and kept in step by gcmTripFormLists (resources/js/gcm-trip-lists.js). --}}
@php($creating = $mode === 'add')

<form id="tripForm" novalidate>
  <div class="alert alert-danger d-none" id="tf-error" role="alert"></div>

  {{-- 1. Basic data --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" data-field="trip_at">
          <label class="form-label" for="tf-trip_at">{{ __('Date & Time') }}</label>
          <input type="datetime-local" class="form-control" id="tf-trip_at" name="trip_at" dir="ltr" required />
        </div>
        <div class="col-md-6" data-field="company_id">
          <label class="form-label" for="tf-company_id">{{ __('Client company') }}</label>
          <select id="tf-company_id" name="company_id" class="form-select" data-placeholder="{{ __('Select a client company') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="project_id">
          <label class="form-label" for="tf-project_id">{{ __('Project') }}</label>
          <select id="tf-project_id" name="project_id" class="form-select" data-placeholder="{{ __('Select a project') }}" required><option value=""></option></select>
        </div>

        {{-- Contractual details --}}
        <div class="col-md-6">
          <div class="row g-4">
            @if ($creating)
              <div class="col-12" data-field="requires_client_approval">
                <label class="form-label d-block">{{ __('The trip needs the client\'s approval') }}</label>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="requires_client_approval" id="tf-rca-yes" value="1" checked /><label class="form-check-label" for="tf-rca-yes">{{ __('Yes') }}</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="requires_client_approval" id="tf-rca-no" value="0" /><label class="form-check-label" for="tf-rca-no">{{ __('No') }}</label></div>
              </div>
            @endif
            <div class="col-12" data-field="requires_purchase_order">
              <label class="form-label d-block">{{ __('The trip needs a purchase order') }}</label>
              <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="requires_purchase_order" id="tf-rpo-yes" value="1" checked /><label class="form-check-label" for="tf-rpo-yes">{{ __('Yes') }}</label></div>
              <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="requires_purchase_order" id="tf-rpo-no" value="0" /><label class="form-check-label" for="tf-rpo-no">{{ __('No') }}</label></div>
              <div class="form-text d-none" id="tf-po-locked">{{ __('No purchase order covers this choice, so the trip goes without one.') }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-6" data-field="purchase_order_id">
          <label class="form-label" for="tf-purchase_order_id">{{ __('Valid purchase orders') }}</label>
          <select id="tf-purchase_order_id" name="purchase_order_id" class="form-select" data-placeholder="{{ __('Select a purchase order') }}"><option value=""></option></select>
          <div class="form-text" id="tf-po-hint"></div>
        </div>

        <div class="col-md-6" data-field="driver_id">
          <label class="form-label" for="tf-driver_id">{{ __('Driver') }}</label>
          <select id="tf-driver_id" name="driver_id" class="form-select" data-placeholder="{{ __('Select a driver') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="vehicle_id">
          <label class="form-label" for="tf-vehicle_id">{{ __('Vehicle') }}</label>
          <select id="tf-vehicle_id" name="vehicle_id" class="form-select" data-placeholder="{{ __('Select a vehicle') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tf-vehicle_type">{{ __('Vehicle type') }}</label>
          <input type="text" class="form-control" id="tf-vehicle_type" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tf-vehicle_capacity">{{ __('Vehicle capacity') }}</label>
          <input type="text" class="form-control" id="tf-vehicle_capacity" readonly dir="ltr" />
        </div>
        <div class="col-md-6" data-field="going_asset_id">
          <label class="form-label" for="tf-going_asset_id">{{ __('Going container / tank') }}</label>
          <select id="tf-going_asset_id" name="going_asset_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="returning_asset_id">
          <label class="form-label" for="tf-returning_asset_id">{{ __('Returning container / tank') }}</label>
          <select id="tf-returning_asset_id" name="returning_asset_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="tf-returning_capacity">{{ __('Returning container capacity') }}</label>
          <input type="text" class="form-control" id="tf-returning_capacity" readonly dir="ltr" />
        </div>

        <div class="col-md-6" data-field="location_url">
          <label class="form-label" for="tf-location_url">{{ __('Trip location') }}</label>
          <div class="input-group">
            <input type="text" class="form-control" id="tf-location_url" name="location_url" dir="ltr" readonly />
            <button type="button" class="btn btn-outline-primary" id="tf-get-location"><i class="ti ti-map-pin me-1"></i>{{ __('Get my location') }}</button>
          </div>
          <div class="form-text"><a href="#" target="_blank" rel="noopener" class="d-none" id="tf-location-link">{{ __('Open in Google Maps') }}</a></div>
        </div>
        <div class="col-md-6" data-field="live_photo">
          <label class="form-label" for="tf-take-photo">{{ __('Live photo from the site') }}</label>
          <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-outline-primary" id="tf-take-photo"><i class="ti ti-camera me-1"></i>{{ __('Take photo') }}</button>
            <button type="button" class="btn btn-text-danger d-none" id="tf-remove-photo"><i class="ti ti-trash me-1"></i>{{ __('Remove') }}</button>
            <span id="tf-photo-existing"></span>
          </div>
          <img id="tf-photo-preview" class="img-fluid rounded border mt-3 d-none" alt="" style="max-height: 160px" />
        </div>
      </div>
    </div>
  </div>

  {{-- 2. Approvals and services --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Approvals & services') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" data-field="client_approver_id">
          <label class="form-label" for="tf-client_approver_id">{{ __('Default client approver') }}</label>
          <select id="tf-client_approver_id" name="client_approver_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="gcm_approver_id">
          <label class="form-label" for="tf-gcm_approver_id">{{ __('Default GCM approver') }}</label>
          <select id="tf-gcm_approver_id" name="gcm_approver_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="main_service_id">
          <label class="form-label" for="tf-main_service_id">{{ __('Main service') }}</label>
          <select id="tf-main_service_id" name="main_service_id" class="form-select" data-placeholder="{{ __('Select a main service') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="sub_service_id">
          <label class="form-label" for="tf-sub_service_id">{{ __('Sub-service') }}</label>
          <select id="tf-sub_service_id" name="sub_service_id" class="form-select" data-placeholder="{{ __('Select a sub-service') }}" @if ($creating) required @endif><option value=""></option></select>
        </div>
      </div>
    </div>
  </div>

  {{-- 3. Quantity, costs and facility --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Quantity, costs & facility') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" data-field="quantity_ton" data-quantity>
          <label class="form-label" for="tf-quantity_ton">{{ __('Waste quantity (ton)') }}</label>
          <input type="number" class="form-control" id="tf-quantity_ton" name="quantity_ton" step="0.001" min="0" dir="ltr" />
          <div class="form-text" id="tf-quantity-hint"></div>
        </div>
        <div class="col-md-6" data-field="quantity_cbm" data-quantity>
          <label class="form-label" for="tf-quantity_cbm">{{ __('Waste quantity (m³)') }}</label>
          <input type="number" class="form-control" id="tf-quantity_cbm" name="quantity_cbm" step="0.001" min="0" dir="ltr" />
        </div>
        <div class="col-md-6" data-field="trip_cost">
          <label class="form-label" for="tf-trip_cost">{{ __('Trip cost') }}</label>
          <input type="number" class="form-control" id="tf-trip_cost" name="trip_cost" step="0.01" min="0" dir="ltr" readonly />
          <div class="form-text" id="tf-cost-hint"></div>
        </div>
        <div class="col-md-6" data-field="ancillary_cost" id="tf-ancillary-wrap">
          <label class="form-label" for="tf-ancillary_cost">{{ __('Ancillary services cost') }}</label>
          <input type="number" class="form-control" id="tf-ancillary_cost" name="ancillary_cost" step="0.01" min="0" dir="ltr" readonly />
        </div>
        <div class="col-md-6" data-field="intermediate_facility_id" data-quantity>
          <label class="form-label" for="tf-intermediate_facility_id">{{ __('Waste disposal & recycle facility') }}</label>
          <select id="tf-intermediate_facility_id" name="intermediate_facility_id" class="form-select" data-placeholder="{{ __('Select a facility') }}"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="other_attachment">
          <label class="form-label" for="tf-other_attachment">{{ __('Other attachments') }}</label>
          <input type="file" class="form-control" id="tf-other_attachment" name="other_attachment" accept=".pdf,.jpg,.jpeg,.png" />
          <div class="form-text" data-existing="other"></div>
        </div>

        <div class="col-md-6" data-field="delivery_note_number">
          <label class="form-label" for="tf-delivery_note_number">{{ __('Delivery Note No.') }}</label>
          <input type="text" class="form-control" id="tf-delivery_note_number" name="delivery_note_number" maxlength="100" dir="ltr" />
        </div>
        <div class="col-md-6" data-field="delivery_note">
          <label class="form-label" for="tf-delivery_note">{{ __('Delivery Note') }}</label>
          <input type="file" class="form-control" id="tf-delivery_note" name="delivery_note" accept=".pdf,.jpg,.jpeg,.png" />
          <div class="form-text" data-existing="delivery-note"></div>
        </div>
        <div class="col-md-6" data-field="waste_manifest_number">
          <label class="form-label" for="tf-waste_manifest_number">{{ __('Waste Manifest No.') }}</label>
          <input type="text" class="form-control" id="tf-waste_manifest_number" name="waste_manifest_number" maxlength="100" dir="ltr" />
        </div>
        <div class="col-md-6" data-field="waste_manifest">
          <label class="form-label" for="tf-waste_manifest">{{ __('Waste Manifest') }}</label>
          <input type="file" class="form-control" id="tf-waste_manifest" name="waste_manifest" accept=".pdf,.jpg,.jpeg,.png" />
          <div class="form-text" data-existing="waste-manifest"></div>
        </div>
        <div class="col-md-6" data-field="recycle_receipt_number" data-recycle>
          <label class="form-label" for="tf-recycle_receipt_number">{{ __('Recycle Receipt No.') }}</label>
          <input type="text" class="form-control" id="tf-recycle_receipt_number" name="recycle_receipt_number" maxlength="100" dir="ltr" />
          <div class="form-text d-none" data-recycle-required>{{ __('This sub-service requires a recycle receipt.') }}</div>
        </div>
        <div class="col-md-6" data-field="recycle_receipt" data-recycle>
          <label class="form-label" for="tf-recycle_receipt">{{ __('Recycle Receipt') }}</label>
          <input type="file" class="form-control" id="tf-recycle_receipt" name="recycle_receipt" accept=".jpg,.jpeg,.png" />
          <div class="form-text" data-existing="recycle-receipt"></div>
        </div>

        <div class="col-12" data-field="additional_data">
          <label class="form-label">{{ __('Additional data') }}</label>
          <div class="comment-editor border" id="tf-additional-data-editor"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- 4. Signatures of the completing step: a trip created without the client's approval (add), or the final approval /
          rejecting the client's objection (edit). Shown only when that step is being taken. --}}
  <div class="card mb-6 d-none" id="tf-completion-card">
    <h5 class="card-header">{{ __('Signatures') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12" id="tf-client-signature-block">
          <h6 class="mb-3">{{ __('The client\'s signature') }}</h6>
          <p class="text-muted small mb-4">{{ __('The trip is completed without the client\'s approval, so upload the client\'s signature.') }}</p>
          <div class="row g-4">
            <div class="col-md-4" data-field="client_signature">
              <label class="form-label" for="tf-client_signature">{{ __('The client\'s signature') }}</label>
              <input type="file" class="form-control" id="tf-client_signature" name="client_signature" accept=".png,.jpg,.jpeg" />
            </div>
            <div class="col-md-4" data-field="client_stamp">
              <label class="form-label" for="tf-client_stamp">{{ __('The client\'s stamp (optional)') }}</label>
              <input type="file" class="form-control" id="tf-client_stamp" name="client_stamp" accept=".png,.jpg,.jpeg" />
            </div>
            <div class="col-md-4" data-field="client_signer_name">
              <label class="form-label" for="tf-client_signer_name">{{ __('Signer\'s name') }}</label>
              <input type="text" class="form-control" id="tf-client_signer_name" name="client_signer_name" maxlength="255" />
            </div>
          </div>
        </div>
        <div class="col-12" data-field="gcm_signature">
          <h6 class="mb-3">{{ __('Your signature') }}</h6>
          <div class="form-check mb-2 d-none" id="tf-saved-signature-wrap">
            <input class="form-check-input" type="radio" name="gcm_signature_source" id="tf-sig-saved" value="saved" />
            <label class="form-check-label" for="tf-sig-saved">{{ __('Use my saved signature') }}</label>
            <img id="tf-saved-signature-img" alt="" class="ms-3 border rounded bg-white" style="height: 48px" />
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="gcm_signature_source" id="tf-sig-draw" value="draw" checked />
            <label class="form-check-label" for="tf-sig-draw">{{ __('Sign now') }}</label>
          </div>
          <div id="tf-signature-pad" style="max-width: 600px"></div>
          <div class="d-flex align-items-center gap-4 mt-3">
            <button type="button" class="btn btn-label-secondary btn-sm" id="tf-sig-clear"><i class="ti ti-eraser me-1"></i>{{ __('Clear') }}</button>
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="tf-save-signature" />
              <label class="form-check-label" for="tf-save-signature">{{ __('Save it in my profile') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2 d-flex flex-wrap gap-3" id="tf-actions">
    <button type="submit" class="btn btn-primary" id="tf-submit">{{ $creating ? __('Create') : __('Save') }}</button>
    @unless ($creating)
      <button type="button" class="btn btn-success d-none" id="tf-final-approve"><i class="ti ti-circle-check me-1"></i>{{ __('Final approval') }}</button>
      <button type="button" class="btn btn-info d-none" id="tf-accept-objection"><i class="ti ti-arrow-back-up me-1"></i>{{ __('Accept the client\'s objection') }}</button>
      <button type="button" class="btn btn-danger d-none" id="tf-reject-objection"><i class="ti ti-x me-1"></i>{{ __('Reject the client\'s objection') }}</button>
      <button type="button" class="btn btn-success d-none" id="tf-confirm-complete"><i class="ti ti-check me-1"></i><span></span></button>
    @endunless
    <a href="{{ $creating ? route('app-trip-list') : route('app-trip-view', $trip->id) }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
  </div>
</form>
