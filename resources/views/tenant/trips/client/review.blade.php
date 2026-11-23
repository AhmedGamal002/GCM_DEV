@extends('layouts/layoutMaster')

@section('title', __('Edit trip data'))

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@php
  $me = auth()->user();
@endphp

@section('page-script')
<script>
  window.clientTripReviewTranslations = {
    ton: @json(__('ton')),
    cbm: @json(__('m³')),
    not_awaiting: @json(__('This trip is no longer awaiting your approval.')),
    sign_first: @json(__('A signature is required: use your saved signature or sign now.')),
    reason_required: @json(__('Explain why the trip is rejected.')),
    approve_title: @json(__('Approve the trip')),
    approve_message: @json(__('Approving the trip requires that the signature entered is your legal signature, since the documents the system generates are used with official bodies; the same applies to the operational stamp. Please confirm.')),
    reject_title: @json(__('Reject the trip')),
    reject_message: @json(__('Rejecting the trip must rest on a serious fault in the service provided under the agreement. Please make sure you have explained the detailed reasons and added any needed attachments.')),
  };
  window.clientTripReviewId = {{ $trip->id }};
  window.clientTripViewUrl = @json(route('app-client-trip-view', $trip->id));
  window.clientTripSaved = {
    signature: @json($me->signature_image ? route('api.users.images.download', ['user' => $me->id, 'type' => 'signature']) : null),
    stamp: @json($me->stamp_image ? route('api.users.images.download', ['user' => $me->id, 'type' => 'stamp']) : null),
  };
  window.clientTripLimits = {
    default: {{ (int) config('trips.attachment_max_kb') }},
    stamp: {{ (int) config('trips.signature_max_kb') }},
  };
</script>
@vite(['resources/assets/js/app-client-trip-review.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Edit trip data No. :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-client-trip-list')],
  ['title' => $trip->code, 'url' => route('app-client-trip-view', $trip->id)],
  ['title' => __('Approve / Reject')],
]])

<div id="ctr-error" class="alert alert-danger d-none"></div>

<div class="card mb-6" id="ctr-loading">
  <div class="card-body text-center py-6"><div class="spinner-border" role="status"></div></div>
</div>

<form id="clientTripReviewForm" class="d-none" novalidate>
  {{-- What is being approved — read-only. --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Trip basic data') }}</h5>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4"><span class="h6">{{ __('Trip No.') }}:</span> <span id="ctr-code" dir="ltr"></span></div>
        <div class="col-md-4"><span class="h6">{{ __('Date') }}:</span> <span id="ctr-date" dir="ltr"></span></div>
        <div class="col-md-4"><span class="h6">{{ __('Project') }}:</span> <span id="ctr-project"></span></div>
        <div class="col-md-4"><span class="h6">{{ __('Driver') }}:</span> <span id="ctr-driver"></span></div>
        <div class="col-md-4"><span class="h6">{{ __('Vehicle No.') }}:</span> <span id="ctr-vehicle" dir="ltr"></span></div>
        <div class="col-md-4"><span class="h6">{{ __('Quantity moved') }}:</span> <span id="ctr-quantity"></span></div>
      </div>
    </div>
  </div>

  {{-- FRD: the main service (fixed) and the sub-service — the one trip field the client may change. --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Service') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="ctr-main-service">{{ __('Main service') }}</label>
          <input type="text" id="ctr-main-service" class="form-control" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ctr-sub-service">{{ __('Sub-service') }}</label>
          <select id="ctr-sub-service" name="sub_service_id" class="select2 form-select" data-allow-clear="false"></select>
          <div class="invalid-feedback"></div>
          <small class="text-muted">{{ __('Only the sub-services that suit the trip\'s container are listed.') }}</small>
        </div>
      </div>
    </div>
  </div>

  {{-- FRD: "التوقيع" (required — saved one or drawn now) and "الختم التشغيلي" (optional). --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Signature & Stamp') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-lg-7" data-required>
          <label class="form-label d-block">{{ __('Responsible signature') }}</label>
          <div class="d-flex gap-4 mb-3" id="ctr-signature-choice">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="signature_source" id="ctr-sig-saved" value="saved">
              <label class="form-check-label" for="ctr-sig-saved">{{ __('Use my saved signature') }}</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="signature_source" id="ctr-sig-draw" value="draw">
              <label class="form-check-label" for="ctr-sig-draw">{{ __('Sign now') }}</label>
            </div>
          </div>
          <div id="ctr-sig-saved-box" class="border rounded p-3 text-center d-none">
            <img id="ctr-sig-saved-img" alt="{{ __('Signature image') }}" style="max-height: 140px; max-width: 100%">
          </div>
          <div id="ctr-sig-draw-box" class="d-none">
            <div id="ctr-signature-pad"></div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="ctr-save-signature">
                <label class="form-check-label" for="ctr-save-signature">{{ __('Save it as my signature') }}</label>
              </div>
              <button type="button" class="btn btn-sm btn-label-secondary" id="ctr-signature-clear">{{ __('Clear') }}</button>
            </div>
          </div>
          <div class="text-danger small mt-2 d-none" id="ctr-signature-error"></div>
        </div>
        <div class="col-lg-5">
          <label class="form-label" for="ctr-stamp">{{ __('Operational stamp') }}</label>
          <div id="ctr-stamp-saved-box" class="border rounded p-3 text-center mb-3 d-none">
            <img id="ctr-stamp-saved-img" alt="{{ __('Stamp image') }}" style="max-height: 120px; max-width: 100%">
            <small class="d-block text-muted mt-2">{{ __('Your saved stamp is used unless you choose another file.') }}</small>
          </div>
          <input type="file" id="ctr-stamp" name="stamp" class="form-control" accept=".png,.jpg,.jpeg">
          <div class="invalid-feedback"></div>
          <small class="text-muted">{{ __('PNG or JPG, up to 2 MB. Used for this trip only.') }}</small>
        </div>
      </div>
    </div>
  </div>

  {{-- FRD: "مرفقات" and "تعليق العميل على الرحلة" (the reasons, required when rejecting). --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Client feedback') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label" for="ctr-comment">{{ __('Client comment on the trip') }}</label>
          <textarea id="ctr-comment" name="comment" class="form-control" rows="4" maxlength="2000"></textarea>
          <div class="invalid-feedback"></div>
          <small class="text-muted">{{ __('Required when you reject the trip: explain the reasons.') }}</small>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ctr-attachment">{{ __('Attachment') }}</label>
          <input type="file" id="ctr-attachment" name="attachment" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
          <div class="invalid-feedback"></div>
          <small class="text-muted">{{ __('PDF or image, up to 5 MB.') }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2 d-flex flex-wrap gap-3">
    <button type="button" class="btn btn-success" id="ctr-approve"><i class="ti ti-check me-1"></i>{{ __('Approve the trip') }}</button>
    <button type="button" class="btn btn-danger" id="ctr-reject"><i class="ti ti-x me-1"></i>{{ __('Reject the trip') }}</button>
    <a href="{{ route('app-client-trip-view', $trip->id) }}" class="btn btn-label-secondary">{{ __('Back') }}</a>
  </div>
</form>

@include('tenant.trips.client._confirm-modal')

@endsection
