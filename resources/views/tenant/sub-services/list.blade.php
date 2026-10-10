@extends('layouts/layoutMaster')

@section('title', __('Sub Services'))

@section('page-script')
<script>
  window.subServiceListTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    non_hazardous: @json(__('Non-Hazardous waste')),
    hazardous: @json(__('Hazardous waste')),
    supplying: @json(__('Supplying')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    recycle_receipt: @json(__('Requires recycle receipt')),
    no_subs_under_main: @json(__('No sub-services under this main service yet.')),
    no_main_services: @json(__('No main services yet — create a main service first.')),
    no_matches: @json(__('No sub-services match your search.')),
    show_more: @json(__('Show more')),
    show_less: @json(__('Show less')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    all_types: @json(__('All types')),
    search_in_all: @json(__('Search in: All')),
    search_in_main: @json(__('Search in: Main services')),
    search_in_sub: @json(__('Search in: Sub-services')),
    placeholder_all: @json(__('Search main or sub-service')),
    placeholder_main: @json(__('Search main service')),
    placeholder_sub: @json(__('Search sub-service')),
    all_statuses: @json(__('All statuses')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    of_total: @json(__(':shown of :total')),
    view_all: @json(__('View all :count')),
    previous: @json(__('Previous')),
    next: @json(__('Next')),
    created: @json(__('Sub-service created successfully.')),
    can_manage: @json(auth()->user()->can('create', \App\Models\SubService::class)),
    main_view_url_base: @json(url('/app/main-service/view')),
    view_url_base: @json(url('/app/sub-service/view')),
    edit_url_base: @json(url('/app/sub-service/edit'))
  };
</script>
@vite('resources/assets/js/app-sub-service-list.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Sub Services'), 'breadcrumbs' => [
  ['title' => __('Services'), 'url' => route('app-main-service-list')],
  ['title' => __('Sub Services')],
]])

<div id="sub-service-list-status" class="alert alert-success d-none"></div>
<div id="sub-service-list-error" class="alert alert-danger d-none"></div>

<div class="card mb-6">
  <div class="card-body">
    {{-- Row 1: the search box takes whatever width is left, the create button keeps its text on one line.
         Row 2: the four selects share the width equally (2 x 2 on a narrow screen, 4 across from md up), so the
         bar never wraps into a ragged second line. --}}
    <div class="d-flex gap-4 align-items-center mb-4">
      <input type="search" id="sub-service-search" class="form-control flex-grow-1" placeholder="{{ __('Search main or sub-service') }}" />
      @can('create', \App\Models\SubService::class)
        <a href="{{ route('app-sub-service-add') }}" class="btn btn-primary flex-shrink-0 text-nowrap" title="{{ __('Create New Sub-service') }}">
          <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline">{{ __('Create New Sub-service') }}</span>
        </a>
      @endcan
    </div>
    <div class="row g-4">
      <div class="col-6 col-md-3">
        <select id="sub-service-search-in" class="form-select" aria-label="{{ __('Search in') }}"></select>
      </div>
      <div class="col-6 col-md-3">
        <select id="sub-service-type-filter" class="form-select" aria-label="{{ __('Type of Service') }}"></select>
      </div>
      <div class="col-6 col-md-3">
        <select id="sub-service-status-filter" class="form-select" aria-label="{{ __('Status') }}"></select>
      </div>
      <div class="col-6 col-md-3">
        <select id="sub-service-per-page" class="form-select">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
  </div>
</div>

<div class="text-center py-6" id="sub-service-list-loading">
  <div class="spinner-border" role="status"></div>
</div>

{{-- FRD §1.9.2: "Accordion With Icon", open by default and collapsible —
     one tab per main service, its sub-services inside. --}}
<div class="accordion" id="subServiceAccordion"></div>

<div class="alert alert-info d-none" id="sub-service-list-empty"></div>

{{-- Server-side pagination over the main services (see app-sub-service-list.js). --}}
<div class="d-none" id="sub-service-footer">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-2 mb-6">
    <div class="text-muted" id="sub-service-info"></div>
    <nav aria-label="{{ __('Sub Services') }}">
      <ul class="pagination mb-0" id="sub-service-pager"></ul>
    </nav>
  </div>
</div>

@endsection
