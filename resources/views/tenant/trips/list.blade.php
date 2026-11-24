@extends('layouts/layoutMaster')

@section('title', __('Trips'))

@section('vendor-style')
@vite([
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
  window.tripListTranslations = {
    can_manage: @json($canManage),
    statuses: @json(collect(\App\Models\Trip::STATUSES)->mapWithKeys(fn ($s) => [$s => \App\Models\Trip::statusLabel($s)])),
    all_statuses: @json(__('All statuses')),
    all_drivers: @json(__('All drivers')),
    all_vehicles: @json(__('All vehicles')),
    all_clients: @json(__('All clients')),
    all_projects: @json(__('All projects')),
    all_main_services: @json(__('All main services')),
    all_sub_services: @json(__('All sub-services')),
    gcm: @json(__('GCM')),
    contractor: @json(__('Contractor')),
    driver_affiliation: @json(__('Driver affiliation')),
    vehicle_affiliation: @json(__('Vehicle affiliation')),
    linked_to: @json(__('Linked to PO')),
    linked: @json(__('Linked to a PO')),
    not_linked: @json(__('Not linked to a PO')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_trip: @json(__('Add Trip')),
    search: @json(__('Search Trip')),
    no_trips_found: @json(__('No trips found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    view_url_base: @json(url('/app/trip/view')),
    edit_url_base: @json(url('/app/trip/edit')),
    add_url: @json(url('/app/trip/add'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-trip-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Trips'), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-trip-list')],
  ['title' => __('List')],
]])

{{-- FRD §1.14 "إدارة الرحلات": the five counters over every trip in the system. --}}
<div class="row g-4 mb-6" id="trip-stat-cards">
  @foreach ([
    ['key' => 'awaiting_client_approval', 'label' => __('Trips awaiting approval'), 'color' => 'warning', 'icon' => 'ti-hourglass'],
    ['key' => 'client_approved',          'label' => __('Approved trips'),          'color' => 'info',    'icon' => 'ti-circle-check'],
    ['key' => 'client_issue',             'label' => __('Rejected trips'),          'color' => 'danger',  'icon' => 'ti-alert-triangle'],
    ['key' => 'completed',                'label' => __('Completed trips'),         'color' => 'success', 'icon' => 'ti-flag-check'],
    ['key' => 'cancelled',                'label' => __('Cancelled trips'),         'color' => 'secondary', 'icon' => 'ti-ban'],
  ] as $c)
    <div class="col-xl col-md-4 col-sm-6">
      <div class="card card-border-shadow-{{ $c['color'] }} h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar me-4">
              <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
            </div>
            <h4 class="mb-0" data-stat="{{ $c['key'] }}">0</h4>
          </div>
          <p class="mb-0 text-heading fw-medium" style="font-size: 17px">{{ $c['label'] }}</p>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="row pt-4 gap-y-4 g-4">
      <div class="col-md-3"><input type="date" class="form-control" id="trip-date-from" aria-label="{{ __('Duration from') }}" title="{{ __('Duration from') }}"></div>
      <div class="col-md-3"><input type="date" class="form-control" id="trip-date-to" aria-label="{{ __('Duration to') }}" title="{{ __('Duration to') }}"></div>
      <div class="col-md-3 trip_status"></div>
      <div class="col-md-3 trip_company"></div>
      <div class="col-md-3 trip_project"></div>
      <div class="col-md-3 trip_driver"></div>
      <div class="col-md-3 trip_vehicle"></div>
      <div class="col-md-3 trip_driver_aff"></div>
      <div class="col-md-3 trip_vehicle_aff"></div>
      <div class="col-md-3 trip_main_service"></div>
      <div class="col-md-3 trip_sub_service"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-trips table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Trip No.') }}</th>
          <th>{{ __('Date') }}</th>
          <th>{{ __('Driver') }}</th>
          <th>{{ __('Driver affiliation') }}</th>
          <th>{{ __('Vehicle') }}</th>
          <th>{{ __('Vehicle affiliation') }}</th>
          <th>{{ __('Client') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Sub-service') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('PO') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
