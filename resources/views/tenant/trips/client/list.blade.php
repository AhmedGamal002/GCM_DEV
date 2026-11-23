@extends('layouts/layoutMaster')

@section('title', __('Trips'))

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
  window.clientTripListTranslations = {
    all_projects: @json(__('All projects')),
    all_main_services: @json(__('All main services')),
    all_sub_services: @json(__('All sub-services')),
    all_statuses: @json(__('All statuses')),
    statuses: @json(collect(\App\Models\Trip::STATUSES)->mapWithKeys(fn ($s) => [$s => \App\Models\Trip::statusLabel($s)])),
    linked: @json(__('Linked to a PO')),
    not_linked: @json(__('Not linked to a PO')),
    view: @json(__('View')),
    review: @json(__('Approve / Reject')),
    actions: @json(__('Actions')),
    search: @json(__('Search Trip')),
    no_trips_found: @json(__('No trips found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    view_url_base: @json(url('/app/client-trip/view')),
    review_url_base: @json(url('/app/client-trip/review'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-client-trip-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Trips'), 'breadcrumbs' => [
  ['title' => __('Trips'), 'url' => route('app-client-trip-list')],
  ['title' => __('List')],
]])

{{-- FRD §1.14 "مراجعة العميل للرحلات": four counters over the trips of the client's projects. --}}
<div class="row g-4 mb-6" id="client-trip-stat-cards">
  @foreach ([
    ['key' => 'awaiting_client_approval', 'label' => __('Trips awaiting approval'), 'color' => 'warning', 'icon' => 'ti-hourglass'],
    ['key' => 'client_approved',          'label' => __('Approved trips'),          'color' => 'info',    'icon' => 'ti-circle-check'],
    ['key' => 'client_issue',             'label' => __('Rejected trips'),          'color' => 'danger',  'icon' => 'ti-alert-triangle'],
    ['key' => 'completed',                'label' => __('Completed trips'),         'color' => 'success', 'icon' => 'ti-flag-check'],
  ] as $c)
    <div class="col-xl-3 col-sm-6">
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
      <div class="col-md-4 ct_project"></div>
      <div class="col-md-4 ct_main_service"></div>
      <div class="col-md-4 ct_sub_service"></div>
      <div class="col-md-4 ct_status"></div>
      <div class="col-md-4">
        <input type="date" class="form-control" id="ct-date-from" aria-label="{{ __('Duration from') }}" title="{{ __('Duration from') }}">
      </div>
      <div class="col-md-4">
        <input type="date" class="form-control" id="ct-date-to" aria-label="{{ __('Duration to') }}" title="{{ __('Duration to') }}">
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-client-trips table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Trip No.') }}</th>
          <th>{{ __('Date') }}</th>
          <th>{{ __('Driver') }}</th>
          <th>{{ __('Vehicle') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Default client approver') }}</th>
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
