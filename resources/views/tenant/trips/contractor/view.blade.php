@extends('layouts/layoutMaster')

@section('title', __('Trip :code', ['code' => $trip->code]))

@section('vendor-script')
@vite(['resources/assets/vendor/libs/moment/moment.js'])
@endsection

@section('page-script')
<script>
  window.contractorTripViewConfig = {
    id: @json($trip->id),
    apiBase: @json(url('/api/v1/trips')),
    t: {
      ton: @json(__('ton')),
      cbm: @json(__('m³')),
      gcm: @json(__('GCM')),
      open_map: @json(__('Open in Google Maps')),
      delivery_note: @json(__('Delivery Note')),
      waste_manifest: @json(__('Waste Manifest'))
    }
  };
</script>
@vite(['resources/assets/js/app-contractor-trip-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Trip :code', ['code' => $trip->code]), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-contractor-trip-list')],
  ['title' => $trip->code],
]])

<div class="alert alert-danger d-none" id="cv-error"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4"><span class="avatar-initial rounded-circle bg-label-primary"><i class="ti ti-truck-delivery ti-xl"></i></span></div>
          <h5 class="mb-1" dir="ltr" data-v="code">—</h5>
          <span class="badge bg-label-secondary" id="cv-status-badge"></span>
        </div>
        <h5 class="pb-4 border-bottom mt-6 mb-4">{{ __('Details') }}</h5>
        <ul class="list-unstyled my-3 py-1">
          @foreach ([
            'date' => __('Date & Time'), 'driver' => __('Driver'), 'vehicle' => __('Vehicle'), 'vehicle_type' => __('Vehicle type'),
            'going' => __('Going container / tank'), 'returning' => __('Returning container / tank'), 'company' => __('Client company'),
            'project' => __('Project'), 'location' => __('Trip location'), 'main_service' => __('Main service'),
            'sub_service' => __('Sub-service'), 'capacity' => __('Container capacity'), 'quantity' => __('Waste quantity'),
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
          <a href="{{ route('app-contractor-trip-list') }}" class="btn btn-label-secondary">{{ __('Back') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- Part 8.1: the delivery note once the trip is completed (the manifest and the combined PDF are not his). --}}
    <div class="card mb-6 d-none" id="cv-generated-card">
      <h5 class="card-header">{{ __('Trip documents') }}</h5>
      <div class="card-body">
        <ul class="list-unstyled mb-0" id="cv-generated"></ul>
        <p class="text-muted small mb-0 d-none" id="cv-generated-hint">{{ __('The trip\'s documents are generated once the trip is completed.') }}</p>
      </div>
    </div>
  </div>
</div>

@endsection
