@extends('layouts/layoutMaster')

@section('title', __('Service Details'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/scss/pages/page-user-view.scss',
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
@endsection

@section('page-script')
<script>
  window.subServiceViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    yes: @json(__('Yes')),
    no: @json(__('No')),
    none: @json(__('None')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    both: @json(__('Both')),
    non_hazardous: @json(__('Non-Hazardous waste')),
    hazardous: @json(__('Hazardous waste')),
    supplying: @json(__('Supplying')),
    disposal: @json(__('Safe disposal')),
    sewage_treatment: @json(__('Sewage treatment')),
    recycle: @json(__('Recycling')),
    saved: @json(__('Changes saved successfully.')),
    created: @json(__('Sub-service created successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    search: @json(__('Search')),
    no_capacities: @json(__('No capacities recorded for this sub-service.')),
    no_facilities: @json(__('No intermediate facilities — not needed for a supplying service.')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    main_view_url_base: @json(url('/app/main-service/view')),
    facility_view_url_base: @json(url('/app/facility/view')),
    edit_url_base: @json(url('/app/sub-service/edit'))
  };
  window.subServiceViewId = {{ $serviceId }};
</script>
@vite('resources/assets/js/app-sub-service-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'ssv-updated-by', 'pageTitle' => __('Service Details'), 'breadcrumbs' => [
  ['title' => __('Sub Services'), 'url' => route('app-sub-service-list')],
  ['title' => __('Service Details')],
]])

<div id="sub-service-view-status" class="alert alert-success d-none"></div>
<div id="sub-service-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="sub-service-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="sub-service-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-recycle ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="ssv-name"></h5>
          <span class="badge" id="ssv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Main service') }}:</span> <span id="ssv-main"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Type of Service') }}:</span> <span id="ssv-type"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Asset type') }}:</span> <span id="ssv-asset-type"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Requires Recycle Receipt') }}:</span> <span id="ssv-receipt"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Skip Color Code') }}:</span> <span id="ssv-color"></span></li>
          <li class="mb-2">
            <span class="h6 d-block mb-1">{{ __('Supported Vehicle Categories') }}:</span>
            <div id="ssv-categories" class="d-flex flex-wrap gap-1"></div>
          </li>
        </ul>

        <div id="ssv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="ssv-additional"></div>
        </div>

        <div class="d-flex justify-content-center">
          @can('create', \App\Models\SubService::class)
            <a href="#" id="ssv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          @endcan
          <a href="{{ route('app-sub-service-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- FRD V01.14 §1.9.2 statistics — built on trips, 0 until they exist. --}}
    @include('tenant.main-services._stats')

    <div class="card mb-6 d-none" id="ssv-capacities-card">
      <h5 class="card-header">{{ __('Compatible container capacities') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="table" id="ssv-capacities-table">
          <thead class="border-top">
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Applies to') }}</th>
              <th>{{ __('Capacity (CBM)') }}</th>
              <th>{{ __('Capacity (TON)') }}</th>
            </tr>
          </thead>
          <tbody id="ssv-capacities-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card mb-6 d-none" id="ssv-facilities-card">
      <h5 class="card-header">{{ __('Waste disposal & recycle facilities') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="table" id="ssv-facilities-table">
          <thead class="border-top">
            <tr>
              <th>{{ __('ID') }}</th>
              <th>{{ __('Facility name') }}</th>
              <th>{{ __('Environmental service') }}</th>
              <th>{{ __('Status') }}</th>
            </tr>
          </thead>
          <tbody id="ssv-facilities-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@endsection
