@extends('layouts/layoutMaster')

@section('title', __('Purchase Orders'))

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
  window.poListTranslations = {
    all_companies: @json(__('All companies')),
    all_projects: @json(__('All projects')),
    all_item_statuses: @json(__('All items statuses')),
    all_account_statuses: @json(__('All statuses')),
    valid: @json(__('In force')),
    near_exhaustion: @json(__('About to run out')),
    exhausted: @json(__('Fully exhausted')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_po: @json(__('Add Purchase Order')),
    search_po: @json(__('Search Purchase Order')),
    no_pos_found: @json(__('No purchase orders found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Purchase order created successfully.')),
    can_manage: @json(auth()->user()->can('create', \App\Models\PurchaseOrder::class)),
    can_export: @json(auth()->user()->can('export', \App\Models\PurchaseOrder::class)),
    // A client manager has no access to the company / project lists (their own are the only ones they see).
    can_filter_lookups: @json(auth()->user()->can('viewAny', \App\Models\Company::class)),
    add_po_url: @json(route('app-po-add')),
    view_url_base: @json(url('/app/po/view')),
    edit_url_base: @json(url('/app/po/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-po-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Purchase Orders'), 'breadcrumbs' => [
  ['title' => __('Purchase Orders'), 'url' => route('app-po-list')],
  ['title' => __('List')],
]])

<div id="po-list-status" class="alert alert-success d-none"></div>

{{-- FRD V01.14 §1.13 statistics: the ACTIVE purchase orders, the companies / projects / trips they cover and the total of their cost items. --}}
<div class="row g-4 mb-6" id="po-stat-cards">
  @foreach ([
    ['key' => 'purchase_orders',   'label' => __('Purchase orders'),     'color' => 'primary', 'icon' => 'ti-file-invoice'],
    ['key' => 'companies',         'label' => __('Companies covered'),   'color' => 'info',    'icon' => 'ti-building'],
    ['key' => 'projects',          'label' => __('Projects covered'),    'color' => 'success', 'icon' => 'ti-briefcase'],
    ['key' => 'trips',             'label' => __('Trips covered'),       'color' => 'warning', 'icon' => 'ti-truck'],
    ['key' => 'cost_items_total',  'label' => __('Total cost items'),    'color' => 'danger',  'icon' => 'ti-coin'],
  ] as $c)
    <div class="col-xl col-lg-4 col-sm-6">
      <div class="card card-border-shadow-{{ $c['color'] }} h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar me-4">
              <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
            </div>
            <h4 class="mb-0" data-stat="{{ $c['key'] }}">0</h4>
          </div>
          <p class="mb-0 text-heading fw-medium" style="font-size: 17px">{{ $c['label'] }}</p>
          @if($c['key'] === 'cost_items_total')
            <small class="text-muted" data-stat-currency></small>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>

<!-- Purchase Orders List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="row pt-4 gap-y-4 g-4">
      <div class="col-md-4 po_company"></div>
      <div class="col-md-4 po_project"></div>
      <div class="col-md-4 po_item_status"></div>
      <div class="col-md-4 po_account_status"></div>
      <div class="col-md-4">
        <input type="date" class="form-control" id="po-date-from" aria-label="{{ __('Duration from') }}" title="{{ __('Duration from') }}">
      </div>
      <div class="col-md-4">
        <input type="date" class="form-control" id="po-date-to" aria-label="{{ __('Duration to') }}" title="{{ __('Duration to') }}">
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-purchase-orders table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Client company') }}</th>
          <th>{{ __('Project') }}</th>
          <th>{{ __('Duration') }}</th>
          <th>{{ __('Days left') }}</th>
          <th>{{ __('Items status') }}</th>
          <th>{{ __('Account status') }}</th>
          <th>{{ __('Details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
