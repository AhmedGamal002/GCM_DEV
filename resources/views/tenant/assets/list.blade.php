@extends('layouts/layoutMaster')

@section('title', __('Assets'))

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
  window.assetListTranslations = {
    all_types: @json(__('All types')),
    all_statuses: @json(__('All statuses')),
    all_affiliations: @json(__('All affiliations')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    active: @json(__('Active')),
    in_project: @json(__('In a project')),
    on_maintenance: @json(__('On Maintenance')),
    deactivated: @json(__('Deactivated')),
    gcm: @json(__('GCM')),
    contractor: @json(__('Contractor')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_asset: @json(__('Add Asset')),
    insert_asset: @json(__('Insert asset into project')),
    can_insert_asset: @json(auth()->user()->can('insertIntoProject', \App\Models\Asset::class)),
    insert_asset_url: @json(route('app-asset-insert-into-project')),
    search_asset: @json(__('Search Asset')),
    no_assets_found: @json(__('No assets found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Asset created successfully.')),
    add_asset_url: @json(route('app-asset-add')),
    view_url_base: @json(url('/app/asset/view')),
    edit_url_base: @json(url('/app/asset/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-asset-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Asset & Supply Hub'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('List')],
]])

<div id="asset-list-status" class="alert alert-success d-none"></div>

@php($assetStatCards = [
  ['key' => 'available',      'label' => __('Available'),      'color' => 'success',   'icon' => 'ti-circle-check'],
  ['key' => 'in_projects',    'label' => __('In projects'),    'color' => 'info',      'icon' => 'ti-briefcase'],
  ['key' => 'on_maintenance', 'label' => __('On Maintenance'), 'color' => 'warning',   'icon' => 'ti-tool'],
  ['key' => 'deactivated',    'label' => __('Deactivated'),    'color' => 'secondary', 'icon' => 'ti-ban'],
])

@foreach (['container' => __('Containers'), 'tank' => __('Tanks')] as $type => $groupLabel)
  <h5 class="mb-3">{{ $groupLabel }}</h5>
  <div class="row g-4 mb-6" data-asset-stats-group="{{ $type }}">
    @foreach ($assetStatCards as $c)
      <div class="col-lg-3 col-sm-6">
        <div class="card card-border-shadow-{{ $c['color'] }} h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <div class="avatar me-4">
                <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
              </div>
              <h4 class="mb-0" data-stat="{{ $type }}.{{ $c['key'] }}">0</h4>
            </div>
            <p class="mb-0 text-heading fw-medium" style="font-size: 17px">{{ $c['label'] }}</p>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endforeach

<!-- Assets List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 asset_type"></div>
      <div class="col-md-4 asset_status"></div>
      <div class="col-md-4 asset_affiliation"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-assets table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('Asset name') }}</th>
          <th>{{ __('Capacity') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Affiliation') }}</th>
          <th>{{ __('Availability') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
