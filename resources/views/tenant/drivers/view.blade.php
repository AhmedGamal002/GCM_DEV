@extends('layouts/layoutMaster')

@section('title', __('Driver Details'))

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-user-view.scss',
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
@endsection

@section('page-script')
<script>
  window.driverViewTranslations = {
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    edit: @json(__('Edit')),
    status_updated: @json(__('Account status updated successfully.')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    edit_url_base: @json(url('/app/driver/edit')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    no_attachment: @json(__('No attachment')),
    download: @json(__('Download')),
    expired: @json(__('Expired')),
    add_permit_url: null,
    search: @json(__('Search')),
    no_entry_permits: @json(__('No entry permits recorded for this driver.')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries'))
  };
  window.driverViewId = {{ $driverId }};
</script>
@vite('resources/assets/js/app-driver-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'dv-updated-by', 'pageTitle' => __('Account Details'), 'breadcrumbs' => [
  ['title' => __('Drivers'), 'url' => route('app-driver-list')],
  ['title' => __('View')],
]])

<div id="driver-view-status" class="alert alert-success d-none"></div>
<div id="driver-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="driver-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="driver-view-content">
        <div class="d-flex align-items-center flex-column">
          <img id="dv-photo" class="rounded-circle mb-4" height="120" width="120" style="object-fit: cover" alt="Avatar" />
          <h5 class="mb-1" id="dv-name"></h5>
          <span class="badge" id="dv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('ID') }}:</span> <span id="dv-code"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Email') }}:</span> <span id="dv-email"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Mobile Number') }}:</span> <span id="dv-phone"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Affiliation') }}:</span> <span id="dv-affiliation"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Default Vehicle') }}:</span> <span id="dv-default-vehicle"></span></li>
        </ul>
        <div id="dv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="dv-additional"></div>
        </div>
        <p class="small text-muted mb-4" id="dv-updated-by"></p>
        <div class="d-flex justify-content-center">
          <a href="#" id="dv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-driver-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="row g-6 mb-6 d-none" id="driver-documents-section">
      <div class="col-md-6">
        <div class="card h-100">
          <h5 class="card-header">{{ __('Residence') }}</h5>
          <div class="card-body">
            <p class="mb-1"><span class="h6">{{ __('Residence Number') }}:</span> <span id="dv-residence-number"></span></p>
            <p class="mb-1"><span class="h6">{{ __('Valid To') }}:</span> <span id="dv-residence-valid-to"></span></p>
            <a href="#" id="dv-residence-download" class="btn btn-sm btn-label-secondary mt-2 d-none"><i class="ti ti-download me-1"></i>{{ __('Download') }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <h5 class="card-header">{{ __('Driving License') }}</h5>
          <div class="card-body">
            <p class="mb-1"><span class="h6">{{ __('License Number') }}:</span> <span id="dv-license-number"></span></p>
            <p class="mb-1"><span class="h6">{{ __('Valid To') }}:</span> <span id="dv-license-valid-to"></span></p>
            <a href="#" id="dv-license-download" class="btn btn-sm btn-label-secondary mt-2 d-none"><i class="ti ti-download me-1"></i>{{ __('Download') }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <h5 class="card-header">{{ __('Operational License') }}</h5>
          <div class="card-body">
            <p class="mb-1"><span class="h6">{{ __('License Number') }}:</span> <span id="dv-operational-license-number"></span></p>
            <p class="mb-1"><span class="h6">{{ __('Valid To') }}:</span> <span id="dv-operational-license-valid-to"></span></p>
            <a href="#" id="dv-operational-license-download" class="btn btn-sm btn-label-secondary mt-2 d-none"><i class="ti ti-download me-1"></i>{{ __('Download') }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <h5 class="card-header">{{ __('Driver Insurance') }}</h5>
          <div class="card-body">
            <p class="mb-1"><span class="h6">{{ __('Insurance Number') }}:</span> <span id="dv-insurance-number"></span></p>
            <p class="mb-1"><span class="h6">{{ __('Valid To') }}:</span> <span id="dv-insurance-valid-to"></span></p>
            <a href="#" id="dv-insurance-download" class="btn btn-sm btn-label-secondary mt-2 d-none"><i class="ti ti-download me-1"></i>{{ __('Download') }}</a>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-6 d-none" id="driver-permits-card">
      <h5 class="card-header">{{ __('Truck Entry Permits') }}</h5>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="dv-entry-permits-table">
          <thead>
            <tr>
              <th>{{ __('Area Name') }}</th>
              <th>{{ __('Permit Number') }}</th>
              <th>{{ __('Valid To') }}</th>
              <th>{{ __('Attachment') }}</th>
            </tr>
          </thead>
          <tbody id="dv-entry-permits-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card d-none" id="driver-status-card">
      <h5 class="card-header">{{ __('Account Status') }}</h5>
      <div class="card-body">
        <div class="alert alert-warning">
          <h5 class="alert-heading mb-1">{{ __('Are you sure you want to change this account\'s status?') }}</h5>
          <p class="mb-0">{{ __("Changing the account status affects the user's ability to log in and interact with the system.") }}</p>
        </div>
        <form id="driverStatusForm">
          <div class="mb-6">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-active" value="active">
              <label class="form-check-label" for="new-status-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-vacation" value="on_vacation">
              <label class="form-check-label" for="new-status-vacation">{{ __('On Vacation') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-deactivated" value="deactivated">
              <label class="form-check-label" for="new-status-deactivated">{{ __('Deactivated') }}</label>
            </div>
          </div>
          <div class="form-check my-8">
            <input class="form-check-input" type="checkbox" id="ds-confirm" />
            <label class="form-check-label" for="ds-confirm">{{ __('I confirm this status change') }}</label>
          </div>
          <button type="submit" class="btn btn-danger" id="ds-submit" disabled>{{ __('Update Status') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
