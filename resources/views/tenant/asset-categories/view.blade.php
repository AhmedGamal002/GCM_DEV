@extends('layouts/layoutMaster')

@section('title', __('Category Details'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss'
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
  window.assetCategoryViewTranslations = {
    container: @json(__('Containers')),
    tank: @json(__('Tanks')),
    both: @json(__('All')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    // (system admin / data entry) only — the auditor gets a read-only page.
    can_manage: @json(auth()->user()->can('create', \App\Models\AssetCapacityCategory::class)),
    edit_url_base: @json(url('/app/asset-category/edit')),
    asset_view_url_base: @json(url('/app/asset/view')),
    asset_type_container: @json(__('Container')),
    asset_type_tank: @json(__('Tank')),
    active: @json(__('Active')),
    on_maintenance: @json(__('On Maintenance')),
    deactivated: @json(__('Deactivated')),
    gcm: @json(__('GCM')),
    contractor: @json(__('Contractor')),
    view: @json(__('View')),
    no_assets_found: @json(__('No assets found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    search_asset: @json(__('Search Asset')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries'))
  };
  window.assetCategoryViewId = {{ $categoryId }};
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-asset-category-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Category Details'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Asset capacity categories'), 'url' => route('app-asset-category-list')],
  ['title' => __('Category Details')],
]])

<div id="asset-category-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="asset-category-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="asset-category-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-ruler-2 ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="acv-name"></h5>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Applies to') }}:</span> <span id="acv-applies-to"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Capacity (CBM)') }}:</span> <span id="acv-capacity-cbm"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Capacity (TON)') }}:</span> <span id="acv-capacity-ton"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Assets') }}:</span> <span id="acv-assets-count"></span></li>
        </ul>
        <div id="acv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional data') }}:</span>
          <div id="acv-additional"></div>
        </div>
        <p class="small text-muted mb-4" id="acv-updated-by"></p>
        <div class="d-flex justify-content-center">
          <a href="#" id="acv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-asset-category-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card">
      <h5 class="card-header">{{ __('Assets') }}</h5>
      <div class="card-datatable table-responsive">
        <table class="datatables-category-assets table table-hover">
          <thead class="border-top">
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Affiliation') }}</th>
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
