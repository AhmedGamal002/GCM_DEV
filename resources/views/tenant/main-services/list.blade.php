@extends('layouts/layoutMaster')

@section('title', __('Main Services'))

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
  window.mainServiceListTranslations = {
    all_types: @json(__('All types')),
    all_statuses: @json(__('All statuses')),
    non_hazardous: @json(__('Non-Hazardous waste')),
    hazardous: @json(__('Hazardous waste')),
    supplying: @json(__('Supplying')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    no_sub_services: @json(__('No sub-services yet')),
    more_link: @json(__('+:count more')),
    more_link_title: @json(__('View all sub-services')),
    show_more: @json(__('Show more')),
    show_less: @json(__('Show less')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_main_service: @json(__('Create New Main Service')),
    search_main_service: @json(__('Search service')),
    no_main_services_found: @json(__('No main services found.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Main service created successfully.')),
    // (system admin / data entry) only — the auditor gets a read-only list.
    can_manage: @json(auth()->user()->can('create', \App\Models\MainService::class)),
    add_url: @json(route('app-main-service-add')),
    view_url_base: @json(url('/app/main-service/view')),
    edit_url_base: @json(url('/app/main-service/edit')),
    sub_view_url_base: @json(url('/app/sub-service/view'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-main-service-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Main Services'), 'breadcrumbs' => [
  ['title' => __('Services'), 'url' => route('app-main-service-list')],
  ['title' => __('Main Services')],
]])

<div id="main-service-list-status" class="alert alert-success d-none"></div>

<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-6 main_service_type"></div>
      <div class="col-md-6 main_service_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-main-services table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Main service name') }}</th>
          <th>{{ __('Type of Service') }}</th>
          <th>{{ __('Sub-services') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
