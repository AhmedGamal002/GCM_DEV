@extends('layouts/layoutMaster')

@section('title', __('Vehicle Details'))

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('page-script')
<script>
  window.vehicleViewTranslations = {
    active: @json(__('Active')),
    on_maintenance: @json(__('On Maintenance')),
    deactivated: @json(__('Deactivated')),
    status_updated: @json(__('Vehicle status updated successfully.')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    gcm: @json(__('GCM')),
    edit_url_base: @json(url('/app/vehicle/edit')),
    doc_labels: {
      registration_card: @json(__('Registration card')),
      fitness_document: @json(__('Fitness document')),
      inspection_certificate: @json(__('Inspection certificate')),
      insurance: @json(__('Vehicle insurance')),
      entry_permit: @json(__('Truck entry permit'))
    }
  };
  window.vehicleViewId = {{ $vehicleId }};
</script>
@vite('resources/assets/js/app-vehicle-view.js')
@endsection

@section('content')

<div id="vehicle-view-status" class="alert alert-success d-none"></div>
<div id="vehicle-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="vehicle-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-12 d-none" id="vehicle-view-content">
        <div class="d-flex align-items-center flex-column">
          <img id="vv-photo" class="img-fluid rounded mb-4" style="width: 100%; height: 260px; object-fit: cover" alt="Vehicle" />
          <h5 class="mb-1" id="vv-plate"></h5>
          <span class="badge" id="vv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Category') }}:</span> <span id="vv-category"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Embedded container') }}:</span> <span id="vv-embedded"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Affiliation') }}:</span> <span id="vv-affiliation"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Entity') }}:</span> <span id="vv-entity"></span></li>
        </ul>
        <div id="vv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="vv-additional"></div>
        </div>
        <div class="d-flex justify-content-center">
          <a href="#" id="vv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-vehicle-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card mb-6 d-none" id="vehicle-photos-card">
      <h5 class="card-header">{{ __('Vehicle Photos') }}</h5>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-sm-6">
            <span class="h6 d-block mb-2">{{ __('Front view') }}</span>
            <div id="vv-photo-front"></div>
          </div>
          <div class="col-sm-6">
            <span class="h6 d-block mb-2">{{ __('Back view') }}</span>
            <div id="vv-photo-back"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-6 d-none" id="vehicle-docs-card">
      <h5 class="card-header">{{ __('Documents') }}</h5>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>{{ __('Document') }}</th>
              <th>{{ __('Number') }}</th>
              <th>{{ __('Valid to') }}</th>
              <th>{{ __('Attachment') }}</th>
            </tr>
          </thead>
          <tbody id="vv-docs-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card mb-6 d-none" id="vehicle-permits-card">
      <h5 class="card-header">{{ __('Truck entry permits') }}</h5>
      <div class="table-responsive d-none" id="vv-permits-table">
        <table class="table">
          <thead>
            <tr>
              <th>{{ __('Area name') }}</th>
              <th>{{ __('Permit number') }}</th>
              <th>{{ __('Valid to') }}</th>
              <th>{{ __('Attachment') }}</th>
            </tr>
          </thead>
          <tbody id="vv-permits-body"></tbody>
        </table>
      </div>
      <div class="card-body d-none" id="vv-permits-empty">
        <p class="mb-0 text-muted">{{ __('No entry permits recorded for this vehicle.') }}</p>
      </div>
    </div>

    <div class="card mb-6">
      <h5 class="card-header">{{ __('Trips') }}</h5>
      <div class="card-body">
        <div class="alert alert-info mb-0">
          {{ __('Trip history and per-vehicle trip statistics will appear here once the Trips module is available.') }}
        </div>
      </div>
    </div>

    <div class="card d-none" id="vehicle-status-card">
      <h5 class="card-header">{{ __('Vehicle Status') }}</h5>
      <div class="card-body">
        <div class="alert alert-warning">
          <h5 class="alert-heading mb-1">{{ __('Change this vehicle\'s operational status?') }}</h5>
          <p class="mb-0">{{ __('A vehicle on maintenance or deactivated cannot be selected for a trip. Deactivation is System Admin only.') }}</p>
        </div>
        <form id="vehicleStatusForm">
          <div class="mb-6">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-active" value="active">
              <label class="form-check-label" for="ns-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-maintenance" value="on_maintenance">
              <label class="form-check-label" for="ns-maintenance">{{ __('On Maintenance') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-deactivated" value="deactivated">
              <label class="form-check-label" for="ns-deactivated">{{ __('Deactivated') }}</label>
            </div>
          </div>
          <div class="form-check my-8">
            <input class="form-check-input" type="checkbox" id="vs-confirm" />
            <label class="form-check-label" for="vs-confirm">{{ __('I confirm this status change') }}</label>
          </div>
          <button type="submit" class="btn btn-danger" id="vs-submit" disabled>{{ __('Update Status') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
