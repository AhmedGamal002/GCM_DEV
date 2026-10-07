@extends('layouts/layoutMaster')

@section('title', __('Main Service Details'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/scss/pages/page-user-view.scss',
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
  window.mainServiceViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    non_hazardous: @json(__('Non-Hazardous waste')),
    hazardous: @json(__('Hazardous waste')),
    supplying: @json(__('Supplying')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    saved: @json(__('Changes saved successfully.')),
    created: @json(__('Main service created successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    search_sub_service: @json(__('Search sub-service')),
    no_sub_services_found: @json(__('No sub-services under this main service yet.')),
    all_asset_types: @json(__('All asset types')),
    export: @json(__('Export')),
    all_statuses: @json(__('All statuses')),
    no_permission: @json(__("You don't have permission to view this data.")),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    add_sub_service: @json(__('Create New Sub-service')),
    can_manage: @json(auth()->user()->can('create', \App\Models\MainService::class)),
    add_sub_url: @json(route('app-sub-service-add')),
    edit_url_base: @json(url('/app/main-service/edit')),
    sub_view_url_base: @json(url('/app/sub-service/view')),
    sub_edit_url_base: @json(url('/app/sub-service/edit'))
  };
  window.mainServiceViewId = {{ $serviceId }};
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-main-service-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'msv-updated-by', 'pageTitle' => __('Main Service Details'), 'breadcrumbs' => [
  ['title' => __('Main Services'), 'url' => route('app-main-service-list')],
  ['title' => __('Main Service Details')],
]])

<div id="main-service-view-status" class="alert alert-success d-none"></div>
<div id="main-service-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="main-service-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="main-service-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-category ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="msv-name"></h5>
          <span class="badge" id="msv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Type of Service') }}:</span> <span id="msv-type"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Sub-services') }}:</span> <span id="msv-sub-count"></span></li>
          <li class="mb-2">
            <span class="h6 d-block mb-1">{{ __('Supported Vehicle Categories') }}:</span>
            <div id="msv-categories" class="d-flex flex-wrap gap-1"></div>
          </li>
        </ul>

        <div id="msv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="msv-additional"></div>
        </div>

        <div class="d-flex justify-content-center">
          @can('create', \App\Models\MainService::class)
            <a href="#" id="msv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          @endcan
          <a href="{{ route('app-main-service-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- FRD V01.14 §1.9.1 statistics — built on trips / POs, which don't
         exist yet, so 0 until then. --}}
    @include('tenant.main-services._stats')

    <div class="card">
      <h5 class="card-header">{{ __('Sub-services under this main service') }}</h5>
      <div class="card-header border-top pt-4 pb-0">
        <div class="row g-4">
          <div class="col-md-4 msv_sub_asset_type"></div>
          <div class="col-md-4 msv_sub_status"></div>
        </div>
      </div>
      <div class="card-datatable table-responsive">
        <table class="datatables-main-service-subs table">
          <thead class="border-top">
            <tr>
              <th>{{ __('Sub-service name') }}</th>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

@endsection
