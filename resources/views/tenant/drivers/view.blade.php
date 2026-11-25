@extends('layouts/layoutMaster')

@section('title', __('Driver Details'))

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-user-view.scss',
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js'
])
@endsection

@section('page-script')
<script>
  // Status changes are for whoever may manage this kind of record (the auditor only views).
  window.gcmCanManage = @json(auth()->user()->can('create', \App\Models\Driver::class));
  
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
    no_documents: @json(__('No documents recorded for this driver.')),
    doc_labels: {
      residence: @json(__('Residence')),
      license: @json(__('Driving License')),
      operational_license: @json(__('Operational License')),
      insurance: @json(__('Driver Insurance'))
    },
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
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-driver-view.js'])
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
    <div class="card mb-6 d-none" id="driver-documents-section">
      <h5 class="card-header">{{ __('Documents') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="table table-hover mb-0" id="dv-docs-table">
          <thead>
            <tr>
              <th>{{ __('Document') }}</th>
              <th>{{ __('Number') }}</th>
              <th>{{ __('Valid To') }}</th>
              <th>{{ __('Time left') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Attachment') }}</th>
            </tr>
          </thead>
          <tbody id="dv-docs-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card mb-6 d-none" id="driver-permits-card">
      <h5 class="card-header">{{ __('Truck Entry Permits') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="table table-hover mb-0" id="dv-entry-permits-table">
          <thead>
            <tr>
              <th>{{ __('Area Name') }}</th>
              <th>{{ __('Permit Number') }}</th>
              <th>{{ __('Valid To') }}</th>
              <th>{{ __('Time left') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Attachment') }}</th>
            </tr>
          </thead>
          <tbody id="dv-entry-permits-body"></tbody>
        </table>
      </div>
    </div>

    @can('viewAny', \App\Models\Trip::class)
      {{-- FRD §1.6: Driver Trips Tracker — completed against ongoing and cancelled trips. --}}
      <div class="card mb-6" id="driver-tracker-card">
        <h5 class="card-header">{{ __('Driver Trips Tracker') }}</h5>
        <div class="card-body">
          <div class="progress mb-4" style="height: 14px" id="dt-bar"></div>
          <div class="d-flex flex-wrap gap-4">
            <span><span class="badge bg-label-success me-1" id="dt-completed">0</span>{{ __('Completed trips') }}</span>
            <span><span class="badge bg-label-info me-1" id="dt-ongoing">0</span>{{ __('Ongoing trips') }}</span>
            <span><span class="badge bg-label-secondary me-1" id="dt-cancelled">0</span>{{ __('Cancelled trips') }}</span>
          </div>
        </div>
      </div>

      @include('tenant.trips._entity-trips', ['kind' => 'driver', 'entityId' => $driverId])
    @endcan

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
