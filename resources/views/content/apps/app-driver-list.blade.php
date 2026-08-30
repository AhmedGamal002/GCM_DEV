@extends('layouts/layoutMaster')

@section('title', __('Drivers'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'
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
  window.driverListTranslations = {
    all_statuses: @json(__('All statuses')),
    all_affiliations: @json(__('All affiliations')),
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_driver: @json(__('Add Driver')),
    search_driver: @json(__('Search Driver')),
    no_drivers_found: @json(__('No drivers found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Driver created successfully.')),
    add_driver_url: @json(route('app-driver-add')),
    view_url_base: @json(url('/app/driver/view')),
    edit_url_base: @json(url('/app/driver/edit'))
  };
</script>
@vite('resources/assets/js/app-driver-list.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('Drivers'), 'url' => route('app-driver-list')],
  ['title' => __('List')],
]])

<div id="driver-list-status" class="alert alert-success d-none"></div>

<!-- Stats — FRD: "احصائيات توضح" (available / on trips / on vacation / deactivated) -->
<div class="row g-6 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">{{ __('Available') }}</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="dl-stat-available">0</h4>
            </div>
            <small class="mb-0">{{ __('Total Available Drivers') }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success"><i class="ti ti-user-check ti-26px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">{{ __('On Trips') }}</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="dl-stat-on-trips">0</h4>
            </div>
            {{-- Trips module doesn't exist yet (Week 7) — always 0 until then. --}}
            <small class="mb-0">{{ __('Total Drivers On Trips') }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-info"><i class="ti ti-route ti-26px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">{{ __('On Vacation') }}</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="dl-stat-vacation">0</h4>
            </div>
            <small class="mb-0">{{ __('Total Drivers On Vacation') }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-warning"><i class="ti ti-beach ti-26px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">{{ __('Deactivated') }}</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2" id="dl-stat-deactivated">0</h4>
            </div>
            <small class="mb-0">{{ __('Total Deactivated Drivers') }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-user-off ti-26px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Drivers List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 driver_affiliation"></div>
      <div class="col-md-4 driver_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-drivers table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Driver Name') }}</th>
          <th>{{ __('Affiliation') }}</th>
          <th>{{ __('Driver Availability') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
