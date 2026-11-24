@extends('layouts/layoutMaster')

@section('title', __('Trip :code', ['code' => $trip->code]))

@section('vendor-script')
@vite(['resources/assets/vendor/libs/moment/moment.js'])
@endsection

@section('page-script')
<script>
  window.driverTripViewConfig = {
    id: @json($trip->id),
    apiBase: @json(url('/api/v1/trips')),
    receiptUrl: @json(url('/api/v1/driver/trips/'.$trip->id.'/recycle-receipt')),
    limits: { default: @json((int) config('trips.attachment_max_kb')) },
    t: {
      ton: @json(__('ton')),
      cbm: @json(__('m³')),
      gcm: @json(__('GCM')),
      open_map: @json(__('Open in Google Maps')),
      receipt_saved: @json(__('The recycle receipt photo was saved.')),
      choose_file: @json(__('Choose the receipt photo first.')),
      delivery_note: @json(__('Delivery Note')),
      waste_manifest: @json(__('Waste Manifest'))
    }
  };
</script>
@vite(['resources/assets/js/app-driver-trip-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Trip :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-driver-trip-list')],
  ['title' => $trip->code],
]])

<div class="alert alert-success d-none" id="dv-flash"></div>
<div class="alert alert-danger d-none" id="dv-error"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4"><span class="avatar-initial rounded-circle bg-label-primary"><i class="ti ti-truck-delivery ti-xl"></i></span></div>
          <h5 class="mb-1" dir="ltr" data-v="code">—</h5>
          <span class="badge bg-label-secondary" id="dv-status-badge"></span>
        </div>
        <h5 class="pb-4 border-bottom mt-6 mb-4">{{ __('Details') }}</h5>
        <ul class="list-unstyled my-3 py-1">
          @foreach ([
            'date' => __('Date & Time'), 'vehicle' => __('Vehicle'), 'vehicle_type' => __('Vehicle type'), 'going' => __('Going container / tank'),
            'returning' => __('Returning container / tank'), 'company' => __('Client company'), 'project' => __('Project'),
            'location' => __('Trip location'), 'main_service' => __('Main service'), 'sub_service' => __('Sub-service'),
            'capacity' => __('Container capacity'), 'quantity' => __('Waste quantity'),
            'facility' => __('Waste disposal & recycle facility'), 'delivery_note' => __('Delivery Note No.'),
            'updated' => __('Last updated'),
          ] as $key => $label)
            <li class="d-flex flex-wrap mb-3" data-row="{{ $key }}">
              <span class="fw-medium me-2">{{ $label }}:</span>
              <span data-v="{{ $key }}">—</span>
            </li>
          @endforeach
        </ul>
        <div class="d-flex justify-content-center pt-2">
          <a href="{{ route('app-driver-trip-list') }}" class="btn btn-label-secondary">{{ __('Back') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Live photo from the site') }}</h5>
      <div class="card-body" id="dv-photo">—</div>
    </div>

    {{-- Part 8.1: the generated documents, once the trip is completed (GET /trips/{id} → documents). --}}
    <div class="card mb-6 d-none" id="dv-generated-card">
      <h5 class="card-header">{{ __('Trip documents') }}</h5>
      <div class="card-body">
        <ul class="list-unstyled mb-0" id="dv-generated"></ul>
        <p class="text-muted small mb-0 d-none" id="dv-generated-hint">{{ __('The trip\'s documents are generated once the trip is completed.') }}</p>
      </div>
    </div>

    {{-- ج13: the driver's one change after creating — the recycle receipt photo, until the trip is completed. --}}
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Recycle Receipt') }}</h5>
      <div class="card-body">
        <div class="mb-4"><span class="fw-medium">{{ __('Current photo') }}:</span> <span id="dv-receipt-current">—</span></div>
        <div id="dv-receipt-form" class="d-none">
          <label class="form-label" for="dv-receipt-file">{{ __('Add or replace the recycle receipt photo') }}</label>
          <div class="input-group">
            <input type="file" class="form-control" id="dv-receipt-file" name="recycle_receipt" accept=".jpg,.jpeg,.png" />
            <button type="button" class="btn btn-primary" id="dv-receipt-upload"><i class="ti ti-upload me-1"></i>{{ __('Upload') }}</button>
          </div>
          <div class="text-danger small mt-2 d-none" id="dv-receipt-error"></div>
        </div>
        <p class="text-muted small mb-0 d-none" id="dv-receipt-closed">{{ __('The trip is completed or cancelled: the receipt can no longer be changed here.') }}</p>
      </div>
    </div>
  </div>
</div>

@endsection
