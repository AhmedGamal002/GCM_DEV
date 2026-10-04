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
  <div class="card-body d-flex flex-column flex-md-row gap-4 justify-content-between align-items-md-center">
    <input type="search" id="sub-service-search" class="form-control w-auto flex-grow-1 flex-md-grow-0" style="min-width: 260px" placeholder="{{ __('Search sub-service') }}" />
    @can('create', \App\Models\SubService::class)
      <a href="{{ route('app-sub-service-add') }}" class="btn btn-primary">
        <i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">{{ __('Create New Sub-service') }}</span>
      </a>
    @endcan
  </div>
</div>

<div class="text-center py-6" id="sub-service-list-loading">
  <div class="spinner-border" role="status"></div>
</div>

{{-- FRD §1.9.2: "Accordion With Icon", open by default and collapsible —
     one tab per main service, its sub-services inside. --}}
<div class="accordion" id="subServiceAccordion"></div>

<div class="alert alert-info d-none" id="sub-service-list-empty"></div>

@endsection
