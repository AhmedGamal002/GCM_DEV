@extends('layouts/layoutMaster')

@section('title', __('Trip details'))

@section('vendor-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/moment/moment.js'])
@endsection

@section('page-script')
<script>
  window.clientTripViewTranslations = {
    last_updated_by: @json(__('Last updated by :name — :at')),
    linked: @json(__('Linked to a PO')),
    not_linked: @json(__('Without a PO')),
    ton: @json(__('ton')),
    cbm: @json(__('m³')),
    open_map: @json(__('Open on the map')),
    approved_flash: @json(__('The trip was approved.')),
    rejected_flash: @json(__('The trip was rejected. GCM will review your comments.')),
    approve_title: @json(__('Approve the trip')),
    approve_message: @json(__('Approving the trip requires that the signature entered is your legal signature, since the documents the system generates are used with official bodies; the same applies to the operational stamp. Please confirm.')),
    approve_saved: @json(__('Your saved signature and stamp will be used.')),
  };
  window.clientTripViewId = {{ $trip->id }};
  window.clientTripHasSavedSignature = @json(auth()->user()->signature_image !== null);
  window.clientTripReviewUrl = @json(route('app-client-trip-review', $trip->id));
</script>
@vite(['resources/assets/js/app-client-trip-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'ctv-updated-by', 'pageTitle' => __('Trip details No. :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-client-trip-list')],
  ['title' => __('Trip details')],
]])

<div id="ctv-status" class="alert alert-success d-none"></div>
<div id="ctv-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="ctv-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="ctv-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-truck-delivery ti-36px"></i></span>
          </div>
          <h5 class="mb-2" id="ctv-code" dir="ltr"></h5>
          <span class="badge" id="ctv-status-badge"></span>
        </div>

        {{-- FRD client details: "البيانات الأساسية للرحلة" --}}
        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Trip basic data') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Trip No.') }}:</span> <span id="ctv-code-2" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Date') }}:</span> <span id="ctv-date" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Driver') }}:</span> <span id="ctv-driver"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Vehicle No.') }}:</span> <span id="ctv-vehicle" dir="ltr"></span></li>
        </ul>

        <div class="d-flex justify-content-center gap-3 flex-wrap">
          <button type="button" id="ctv-approve" class="btn btn-success d-none"><i class="ti ti-check me-1"></i>{{ __('Approve the trip') }}</button>
          <a href="{{ route('app-client-trip-review', $trip->id) }}" id="ctv-review" class="btn btn-primary d-none"><i class="ti ti-edit me-1"></i>{{ __('Edit') }}</a>
          <a href="{{ route('app-client-trip-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- "البيانات الأساسية للعميل" --}}
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Client basic data') }}</h5>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><span class="h6">{{ __('Client company') }}:</span> <span id="ctv-company"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Project') }}:</span> <span id="ctv-project"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Field officer in the project') }}:</span> <span id="ctv-representative"></span></li>
          <li class="mb-0"><span class="h6">{{ __('Client location') }}:</span> <span id="ctv-location"></span></li>
        </ul>
      </div>
    </div>

    {{-- "البيانات الأساسية للخدمة" --}}
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Service basic data') }}</h5>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><span class="h6">{{ __('Purchase order') }}:</span> <span id="ctv-po"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Main service') }}:</span> <span id="ctv-main-service"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Sub-service') }}:</span> <span id="ctv-sub-service"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Quantity moved') }}:</span> <span id="ctv-quantity"></span></li>
          <li class="mb-0"><span class="h6">{{ __('Live photo') }}:</span> <span id="ctv-photo">—</span></li>
        </ul>
      </div>
    </div>

    {{-- "ملاحظات العميل" / "مرفقات العميل" --}}
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Client feedback') }}</h5>
      <div class="card-body">
        <p class="mb-4" id="ctv-comment" style="white-space: pre-line">—</p>
        <div><span class="h6">{{ __('Client attachments') }}:</span> <span id="ctv-attachment">—</span></div>
      </div>
    </div>
  </div>
</div>

@include('tenant.trips.client._confirm-modal')

@endsection
