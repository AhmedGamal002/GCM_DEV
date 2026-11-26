@extends('layouts/layoutMaster')

@section('title', __('Reports'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/apex-charts/apexcharts.js'
])
@endsection

@section('page-script')
<script>
  window.reportsConfig = {
    seesCharts: @json($seesCharts),
    t: {
      all: @json(__('All')),
      trips: @json(__('Trips')),
      tons: @json(__('ton')),
      no_trips: @json(__('No trips found.')),
      no_permission: @json(__("You don't have permission to view this data.")),
      info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
      info_empty: @json(__('Showing 0 to 0 of 0 entries'))
    }
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-reports.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Reports and statistics'), 'breadcrumbs' => [
  ['title' => __('Reports')],
]])

{{-- The nine filters (FRD: "قابلة للتخصيص على مستوي عناصر التصفية"); they drive the figures, the charts, the table and the Excel. --}}
<div class="card mb-6">
  <h5 class="card-header">{{ __('Filters') }}</h5>
  <div class="card-body">
    <div class="row g-4" id="rp-filters">
      <div class="col-md-3"><label class="form-label" for="rp-period">{{ __('Time range') }}</label><select class="form-select" id="rp-period" data-key="period"></select></div>
      <div class="col-md-3 d-none" data-custom><label class="form-label" for="rp-date-from">{{ __('Duration from') }}</label><input type="date" class="form-control" id="rp-date-from"></div>
      <div class="col-md-3 d-none" data-custom><label class="form-label" for="rp-date-to">{{ __('Duration to') }}</label><input type="date" class="form-control" id="rp-date-to"></div>
      <div class="col-md-3"><label class="form-label" for="rp-company">{{ __('Client company') }}</label><select class="form-select" id="rp-company" data-key="company_id" data-list="companies"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-project">{{ __('Project') }}</label><select class="form-select" id="rp-project" data-key="project_id" data-list="projects"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-sub-service">{{ __('Sub-service') }}</label><select class="form-select" id="rp-sub-service" data-key="sub_service_id" data-list="sub_services"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-status">{{ __('Trip approval status') }}</label><select class="form-select" id="rp-status" data-key="status" data-list="statuses"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-vehicle">{{ __('Vehicle') }}</label><select class="form-select" id="rp-vehicle" data-key="vehicle_id" data-list="vehicles"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-driver">{{ __('Driver') }}</label><select class="form-select" id="rp-driver" data-key="driver_id" data-list="drivers"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-contractor">{{ __('Subcontractor') }}</label><select class="form-select" id="rp-contractor" data-key="contractor_id" data-list="contractors"></select></div>
      <div class="col-md-3"><label class="form-label" for="rp-facility">{{ __('Recycling facility') }}</label><select class="form-select" id="rp-facility" data-key="facility_id" data-list="facilities"></select></div>
    </div>
  </div>
</div>

@if ($seesCharts)
  {{-- The headline figures and the charts of the filtered trips (a cancelled trip only counts in the statuses). --}}
  <div class="row g-4 mb-6" id="rp-totals">
    @foreach ([
      ['key' => 'trips', 'label' => __('Trips'), 'color' => 'primary', 'icon' => 'ti-truck-delivery'],
      ['key' => 'quantity_ton', 'label' => __('Waste moved (ton)'), 'color' => 'success', 'icon' => 'ti-recycle'],
      ['key' => 'quantity_cbm', 'label' => __('Waste moved (m³)'), 'color' => 'info', 'icon' => 'ti-droplet'],
      ['key' => 'vehicles', 'label' => __('Vehicles used'), 'color' => 'warning', 'icon' => 'ti-truck'],
      ['key' => 'drivers', 'label' => __('Drivers'), 'color' => 'secondary', 'icon' => 'ti-steering-wheel'],
    ] as $c)
      <div class="col-xl col-md-4 col-sm-6">
        <div class="card card-border-shadow-{{ $c['color'] }} h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <div class="avatar me-4"><span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span></div>
              <h4 class="mb-0" data-total="{{ $c['key'] }}">0</h4>
            </div>
            <p class="mb-0 text-heading fw-medium">{{ $c['label'] }}</p>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-6 mb-6">
    <div class="col-lg-8">
      <div class="card h-100"><h5 class="card-header">{{ __('Trips over the period') }}</h5><div class="card-body"><div id="rp-timeline"></div></div></div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100"><h5 class="card-header">{{ __('Trips by status') }}</h5><div class="card-body"><div id="rp-statuses"></div></div></div>
    </div>
    <div class="col-12">
      <div class="card"><h5 class="card-header">{{ __('Sub-services: trips and waste (ton)') }}</h5><div class="card-body"><div id="rp-sub-services"></div></div></div>
    </div>
  </div>
@endif

{{-- The trips summary (FRD "تقرير ملخص الرحلات (Trips Summary)") — the table, and the Excel of the same rows. --}}
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <h5 class="mb-0">{{ __('Trips Summary') }}</h5>
    <button type="button" class="btn btn-primary" id="rp-export"><i class="ti ti-file-spreadsheet me-1"></i>{{ __('Export Excel') }}</button>
  </div>
  <div class="card-datatable table-responsive">
    <table class="table" id="rp-table">
      <thead class="border-top">
        <tr>
          <th>{{ __('Trip No.') }}</th>
          <th>{{ __('Client company') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Date') }}</th>
          <th>{{ __('Time') }}</th>
          <th>{{ __('Delivery Note No.') }}</th>
          <th>{{ __('Waste Manifest No.') }}</th>
          <th>{{ __('Sub-service') }}</th>
          <th>{{ __('Container capacity') }}</th>
          <th>{{ __('Trip status') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
