@extends('layouts/layoutMaster')

@section('title', __('My trips'))

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
  window.driverTripListTranslations = {
    statuses: @json(collect(\App\Models\Trip::STATUSES)->mapWithKeys(fn ($s) => [$s => \App\Models\Trip::statusLabel($s)])),
    all_statuses: @json(__('All statuses')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_trip: @json(__('Add Trip')),
    search: @json(__('Search Trip')),
    no_trips_found: @json(__('No trips found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    view_url_base: @json(url('/app/driver-trip/view')),
    add_url: @json(url('/app/driver-trip/add'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-driver-trip-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('My trips'), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-driver-trip-list')],
  ['title' => __('List')],
]])

<div class="card">
  <div class="card-header border-bottom">
    <div class="row g-4">
      <div class="col-md-4 dt_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-driver-trips table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Trip No.') }}</th>
          <th>{{ __('Date') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Vehicle') }}</th>
          <th>{{ __('Sub-service') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
