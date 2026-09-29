@extends('layouts/layoutMaster')

@section('title', __('Intermediate Facilities'))

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
  window.facilityListTranslations = {
    all_services: @json(__('All services')),
    all_statuses: @json(__('All statuses')),
    disposal: @json(__('Safe disposal')),
    sewage_treatment: @json(__('Sewage treatment')),
    recycle: @json(__('Recycling')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    recycling_efficiency: @json(__('Recycling efficiency')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_facility: @json(__('Create new facility')),
    search_facility: @json(__('Search facility')),
    no_facilities_found: @json(__('No facilities found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Facility created successfully.')),
    // (system admin / data entry) only — the auditor gets a read-only list.
    can_manage: @json(auth()->user()->can('create', \App\Models\IntermediateFacility::class)),
    add_facility_url: @json(route('app-facility-add')),
    view_url_base: @json(url('/app/facility/view')),
    edit_url_base: @json(url('/app/facility/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-facility-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Intermediate Facilities'), 'breadcrumbs' => [
  ['title' => __('Facilities'), 'url' => route('app-facility-list')],
  ['title' => __('List')],
]])

<div id="facility-list-status" class="alert alert-success d-none"></div>

@php($serviceCards = [
  ['key' => 'disposal',         'label' => __('Safe disposal'),     'color' => 'primary', 'icon' => 'ti-trash'],
  ['key' => 'sewage_treatment', 'label' => __('Sewage treatment'),  'color' => 'info',    'icon' => 'ti-droplet'],
  ['key' => 'recycle',          'label' => __('Recycling'),         'color' => 'success', 'icon' => 'ti-recycle'],
])

<div class="row g-4 mb-6" id="facility-stat-cards">
  @foreach ($serviceCards as $c)
    <div class="col-lg-4 col-sm-6">
      <div class="card card-border-shadow-{{ $c['color'] }} h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar me-4">
              <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
            </div>
            <h4 class="mb-0" data-stat="{{ $c['key'] }}.total">0</h4>
          </div>
          <p class="mb-0 text-heading fw-medium" style="font-size: 17px">{{ $c['label'] }}</p>
          <small class="text-muted"><span data-stat="{{ $c['key'] }}.active">0</span> {{ __('active') }}</small>
        </div>
      </div>
    </div>
  @endforeach
</div>

<!-- Facilities List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-6 facility_service"></div>
      <div class="col-md-6 facility_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-facilities table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Facility name') }}</th>
          <th>{{ __('Environmental service') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
