@extends('layouts/layoutMaster')

@section('title', __('Contractors'))

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
  window.contractorListTranslations = {
    all_statuses: @json(__('All statuses')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_contractor: @json(__('Add Contractor')),
    search_contractor: @json(__('Search Contractor')),
    no_contractors_found: @json(__('No contractors found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Contractor created successfully.')),
    can_manage: @json(auth()->user()->can('create', \App\Models\Contractor::class)),
    add_contractor_url: @json(route('app-contractor-add')),
    view_url_base: @json(url('/app/contractor/view')),
    edit_url_base: @json(url('/app/contractor/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-contractor-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Contractors'), 'breadcrumbs' => [
  ['title' => __('Contractors'), 'url' => route('app-contractor-list')],
  ['title' => __('List')],
]])

<div id="contractor-list-status" class="alert alert-success d-none"></div>

{{-- FRD V01.14 §1.10 statistics: contractors, and the vehicles / assets / drivers they supply. --}}
<div class="row g-4 mb-6" id="contractor-stat-cards">
  @foreach ([
    ['key' => 'contractors', 'label' => __('Contractors'), 'color' => 'primary', 'icon' => 'ti-building-community', 'subs' => [['contractors.active', __('Active')]]],
    ['key' => 'vehicles',    'label' => __('Vehicles'),    'color' => 'info',    'icon' => 'ti-truck'],
    ['key' => 'assets',      'label' => __('Assets'),      'color' => 'success', 'icon' => 'ti-box', 'subs' => [['assets.container', __('Containers')], ['assets.tank', __('Tanks')]]],
    ['key' => 'drivers',     'label' => __('Drivers'),     'color' => 'warning', 'icon' => 'ti-steering-wheel'],
  ] as $c)
    <div class="col-lg-3 col-sm-6">
      <div class="card card-border-shadow-{{ $c['color'] }} h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar me-4">
              <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
            </div>
            <h4 class="mb-0" data-stat="{{ $c['key'] === 'contractors' ? 'contractors.total' : $c['key'] }}">0</h4>
          </div>
          <p class="mb-0 text-heading fw-medium" style="font-size: 17px">{{ $c['label'] }}</p>
          @if(isset($c['subs']))
            <small class="text-muted">
              @foreach($c['subs'] as [$statKey, $statLabel])
                {{ $statLabel }}: <span data-stat="{{ $statKey }}">0</span>@if(! $loop->last) · @endif
              @endforeach
            </small>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>

<!-- Contractors List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 contractor_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-contractors table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Contractor') }}</th>
          <th>{{ __('Drivers') }}</th>
          <th>{{ __('Vehicles') }}</th>
          <th>{{ __('Containers') }}</th>
          <th>{{ __('Tanks') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
