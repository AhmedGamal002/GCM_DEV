@extends('layouts/layoutMaster')

@section('title', __('Dashboard'))

@section('vendor-style')
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/apex-charts/apexcharts.js'
])
@endsection

@section('page-script')
<script>
  window.dashboardConfig = {
    urls: {
      po_list: @json(url('/app/po/list')),
      notifications: @json(route('app-notifications'))
    },
    t: {
      trips_chart: @json(__('Trips')),
      active: @json(__('Active')),
      on_vacation: @json(__('On Vacation')),
      on_maintenance: @json(__('On Maintenance')),
      deactivated: @json(__('Deactivated')),
      valid: @json(__('In force')),
      near_exhaustion: @json(__('About to run out')),
      exhausted: @json(__('Fully exhausted')),
      trips_word: @json(__('trips')),
      no_notifications: @json(__('No notifications yet.')),
      no_trips: @json(__('No trips yet.')),
      new_label: @json(__('New')),
      drivers_label: @json(__('Drivers')),
      vehicles_label: @json(__('Vehicles')),
      containers_label: @json(__('Containers')),
      tanks_label: @json(__('Tanks'))
    }
  };
</script>
@vite(['resources/assets/js/app-dashboard.js'])
@endsection

@section('content')
<div class="row g-6">
  <div class="col-12">
    <div class="card">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h4 class="mb-2">{{ __('Welcome') }}, {{ auth()->user()->name }}</h4>
          <p class="mb-0">
            {{ auth()->user()->tenant->name }} &mdash; {{ auth()->user()->getRoleNames()->first() }}
          </p>
        </div>
        {{-- FRD §1.14: the driver creates his trips from his dashboard. --}}
        @can('createAsDriver', \App\Models\Trip::class)
          <a href="{{ route('app-driver-trip-add') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('Add Trip') }}</a>
        @endcan
      </div>
    </div>
  </div>

  {{-- The five trip statuses (the whole of what the user may see). --}}
  <div class="col-12 d-none" id="db-statuses">
    <div class="row g-4" id="db-status-cards"></div>
  </div>

  {{-- Trips over the last 30 days, and how the main services share them. --}}
  <div class="col-lg-8 col-12 d-none" id="db-trips-card">
    <div class="card h-100">
      <h5 class="card-header">{{ __('Trips in the last 30 days') }}</h5>
      <div class="card-body"><div id="db-trips-chart"></div></div>
    </div>
  </div>
  <div class="col-lg-4 col-12 d-none" id="db-services-card">
    <div class="card h-100">
      <h5 class="card-header">{{ __('Main services share of the trips') }}</h5>
      <div class="card-body"><div id="db-services-chart"></div></div>
    </div>
  </div>

  {{-- Resources: drivers, vehicles, containers, tanks by status. --}}
  <div class="col-12 d-none" id="db-resources">
    <div class="row g-4" id="db-resource-cards"></div>
  </div>

  {{-- Purchase orders: in force / about to run out / fully exhausted. --}}
  <div class="col-12 d-none" id="db-pos">
    <div class="row g-4" id="db-po-cards"></div>
  </div>

  {{-- The latest ten notifications of the user. --}}
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Latest notifications') }}</h5>
        <a href="{{ route('app-notifications') }}" class="btn btn-sm btn-label-primary">{{ __('View all notifications') }}</a>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead class="border-top">
            <tr>
              <th>{{ __('Date & Time') }}</th>
              <th>{{ __('Notification type') }}</th>
              <th>{{ __('Notification details') }}</th>
            </tr>
          </thead>
          <tbody id="db-notifications"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
