@extends('layouts/layoutMaster')

@section('title', __('Vehicles'))

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
  window.vehicleListTranslations = {
    all_categories: @json(__('All categories')),
    all_statuses: @json(__('All statuses')),
    all_affiliations: @json(__('All affiliations')),
    active: @json(__('Active')),
    on_maintenance: @json(__('On Maintenance')),
    deactivated: @json(__('Deactivated')),
    gcm: @json(__('GCM')),
    contractor: @json(__('Contractor')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_vehicle: @json(__('Add Vehicle')),
    search_vehicle: @json(__('Search Vehicle')),
    no_vehicles_found: @json(__('No vehicles found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Vehicle created successfully.')),
    trips_count: @json(__('Trips')),
    add_vehicle_url: @json(route('app-vehicle-add')),
    view_url_base: @json(url('/app/vehicle/view')),
    edit_url_base: @json(url('/app/vehicle/edit')),
    categories: {
      hook_lift: @json(__('Hook lift system')),
      compactor: @json(__('Compactor unit')),
      dump_truck: @json(__('Dump truck')),
      water_tanker: @json(__('Water tanker')),
      dyna_box: @json(__('Dyna box'))
    }
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-vehicle-list.js'])
@endsection

@section('content')

<div id="vehicle-list-status" class="alert alert-success d-none"></div>

@php($vehicleStatCards = [
  ['key' => 'available',      'label' => __('Available'),      'color' => 'success',   'icon' => 'ti-circle-check'],
  ['key' => 'on_trip',        'label' => __('On a trip'),      'color' => 'info',      'icon' => 'ti-route'],
  ['key' => 'on_maintenance', 'label' => __('On Maintenance'), 'color' => 'warning',   'icon' => 'ti-tool'],
  ['key' => 'deactivated',    'label' => __('Deactivated'),    'color' => 'secondary', 'icon' => 'ti-ban'],
])

<div class="row g-4 mb-6" id="vehicle-stats">
  @foreach ($vehicleStatCards as $c)
    <div class="col-lg-3 col-sm-6">
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

@php($vehicleCategoryCards = [
  'hook_lift'    => __('Hook lift system'),
  'compactor'    => __('Compactor unit'),
  'dump_truck'   => __('Dump truck'),
  'water_tanker' => __('Water tanker'),
  'dyna_box'     => __('Dyna box'),
])

<div class="card mb-6">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Vehicles by category') }}</h5>
    <div class="row pt-4 g-4" id="vehicle-category-stats">
      @foreach ($vehicleCategoryCards as $slug => $label)
        <div class="col-6 col-md">
          <span class="d-block small text-muted" style="font-size: 15px">{{ $label }}</span>
          <span class="h5" data-cat="{{ $slug }}">0</span>
        </div>
      @endforeach
    </div>
  </div>
</div>

<!-- Vehicles List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 vehicle_category"></div>
      <div class="col-md-4 vehicle_status"></div>
      <div class="col-md-4 vehicle_affiliation"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-vehicles table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('Plate') }}</th>
          <th>{{ __('Category') }}</th>
          <th>{{ __('Trips') }}</th>
          <th>{{ __('Affiliation') }}</th>
          <th>{{ __('Availability') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
