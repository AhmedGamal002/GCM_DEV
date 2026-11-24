@extends('layouts/layoutMaster')

@section('title', __('Create a new trip'))

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
<script>
  window.driverTripAddConfig = {
    apiBase: @json(url('/api/v1/driver/trips')),
    viewUrlBase: @json(url('/app/driver-trip/view')),
    limits: { default: @json((int) config('trips.attachment_max_kb')) },
    t: {
      cleared_sub_service: @json(__('The sub-service was cleared: it does not suit the container or tank you chose.')),
      need_location: @json(__('The trip\'s live location is required.')),
      need_photo: @json(__('A live photo from the site is required.')),
      location_ok: @json(__('Location saved.')),
      photo_ok: @json(__('Photo ready.')),
      locating: @json(__('Finding your location…')),
      choose_both: @json(__('Take the live location and the live photo to create the trip.'))
    }
  };
</script>
@vite(['resources/assets/js/app-driver-trip-add.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create a new trip'), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-driver-trip-list')],
  ['title' => __('Add')],
]])

{{-- FRD §1.14 "من منظور دور السائق": the minimum of data. The date is the moment of creation, the driver is the account. --}}
<form id="driverTripForm" novalidate>
  <div class="alert alert-danger d-none" id="df-error" role="alert"></div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" data-field="company_id">
          <label class="form-label" for="df-company_id">{{ __('Client company') }}</label>
          <select id="df-company_id" name="company_id" class="form-select" data-placeholder="{{ __('Select a client company') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="project_id">
          <label class="form-label" for="df-project_id">{{ __('Project') }}</label>
          <select id="df-project_id" name="project_id" class="form-select" data-placeholder="{{ __('Select a project') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="vehicle_id">
          <label class="form-label" for="df-vehicle_id">{{ __('Vehicle') }}</label>
          <select id="df-vehicle_id" name="vehicle_id" class="form-select" data-placeholder="{{ __('Select a vehicle') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Vehicle type') }}</label>
          <div class="form-control-plaintext" id="df-vehicle_type">—</div>
        </div>
        <div class="col-md-6" data-field="main_service_id">
          <label class="form-label" for="df-main_service_id">{{ __('Main service') }}</label>
          <select id="df-main_service_id" name="main_service_id" class="form-select" data-placeholder="{{ __('Select a main service') }}" required><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="sub_service_id">
          <label class="form-label" for="df-sub_service_id">{{ __('Sub-service') }}</label>
          <select id="df-sub_service_id" name="sub_service_id" class="form-select" data-placeholder="{{ __('Select a sub-service') }}"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="going_asset_id">
          <label class="form-label" for="df-going_asset_id">{{ __('Going container / tank') }}</label>
          <select id="df-going_asset_id" name="going_asset_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
        <div class="col-md-6" data-field="returning_asset_id">
          <label class="form-label" for="df-returning_asset_id">{{ __('Returning container / tank') }}</label>
          <select id="df-returning_asset_id" name="returning_asset_id" class="form-select" data-placeholder="—"><option value=""></option></select>
        </div>
      </div>
    </div>
  </div>

  {{-- Both are live (ج12): the camera and the GPS are the only ways in — no gallery, no typed link. --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('On site') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" data-field="location_url">
          <label class="form-label">{{ __('Trip location') }} <span class="text-danger">*</span></label>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <button type="button" class="btn btn-outline-primary" id="df-get-location"><i class="ti ti-map-pin me-1"></i>{{ __('Get my location') }}</button>
            <span class="small" id="df-location-status"></span>
          </div>
          <a href="#" target="_blank" rel="noopener" class="small d-none" id="df-location-link">{{ __('Open in Google Maps') }}</a>
        </div>
        <div class="col-md-6" data-field="live_photo">
          <label class="form-label">{{ __('Live photo from the site') }} <span class="text-danger">*</span></label>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <button type="button" class="btn btn-outline-primary" id="df-take-photo"><i class="ti ti-camera me-1"></i>{{ __('Take photo') }}</button>
            <span class="small" id="df-photo-status"></span>
          </div>
          <img id="df-photo-preview" class="img-fluid rounded border mt-3 d-none" alt="" style="max-height: 180px" />
        </div>
        <div class="col-md-6" data-field="other_attachment">
          <label class="form-label" for="df-other_attachment">{{ __('Other attachments') }}</label>
          <input type="file" class="form-control" id="df-other_attachment" name="other_attachment" accept=".pdf,.jpg,.jpeg,.png" />
        </div>
        <div class="col-12" data-field="additional_data">
          <label class="form-label" for="df-additional_data">{{ __('Additional data') }}</label>
          <textarea class="form-control" id="df-additional_data" rows="3" maxlength="5000"></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2 d-flex flex-wrap align-items-center gap-3">
    <button type="submit" class="btn btn-primary" id="df-submit" disabled>{{ __('Create') }}</button>
    <a href="{{ route('app-driver-trip-list') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
    <span class="text-muted small" id="df-hint"></span>
  </div>
</form>

@endsection
