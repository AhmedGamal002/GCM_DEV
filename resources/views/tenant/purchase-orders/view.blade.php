@extends('layouts/layoutMaster')

@section('title', __('Purchase Order Details'))

@section('vendor-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('page-script')
<script>
  window.poViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    saved: @json(__('Changes saved successfully.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    download: @json(__('Download')),
    trips_only: @json(__('Trips cost only')),
    trips_and_ancillary: @json(__('Trips cost and ancillary services cost')),
    component_date: @json(__('Days')),
    component_trips: @json(__('Trips')),
    component_trip_cost: @json(__('Trip cost')),
    component_ancillary_cost: @json(__('Ancillary services cost')),
    max_trips: @json(__('Max number of trips')),
    trip_cost: @json(__('Trip cost')),
    ancillary_cost_per_trip: @json(__('Ancillary services cost per trip')),
    alert_percent: @json(__('Expiry alert percentage')),
    max_trip_cost: @json(__('Maximum trips cost')),
    max_ancillary_cost: @json(__('Maximum ancillary services cost')),
    used: @json(__('Used')),
    remaining: @json(__('Remaining')),
    of: @json(__('of')),
    no_services: @json(__('This purchase order has no services.')),
    company_view_url_base: @json(url('/app/company/view')),
    project_view_url_base: @json(url('/app/project/view')),
    edit_url_base: @json(url('/app/po/edit')),
    // The project page is the staff's; a client manager has no project pages of their own.
    can_open_project: @json(auth()->user()->can('viewAny', \App\Models\Project::class)),
    can_open_company: @json(auth()->user()->can('viewAny', \App\Models\Company::class))
  };
  window.poViewId = {{ $purchaseOrderId }};
</script>
@vite(['resources/assets/js/app-po-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'pov-updated-by', 'pageTitle' => __('Purchase Order Details'), 'breadcrumbs' => [
  ['title' => __('Purchase Orders'), 'url' => route('app-po-list')],
  ['title' => __('Purchase Order Details')],
]])

<div id="po-view-status" class="alert alert-success d-none"></div>
<div id="po-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="po-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="po-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-file-invoice ti-36px"></i></span>
          </div>
          <h5 class="mb-2" id="pov-number" dir="ltr"></h5>
          <div class="d-flex gap-2 flex-wrap justify-content-center">
            <span class="badge" id="pov-account-status"></span>
            <span class="badge" id="pov-items-status"></span>
          </div>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('PO No.') }}:</span> <span id="pov-number-2" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Client company') }}:</span> <span id="pov-company"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Project') }}:</span> <span id="pov-project"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Operational region') }}:</span> <span id="pov-region"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Duration') }}:</span> <span id="pov-duration" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Days left') }}:</span> <span id="pov-days"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Items status') }}:</span> <span id="pov-items-label"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Attachment') }}:</span> <span id="pov-attachment">—</span></li>
        </ul>

        <div id="pov-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="pov-additional"></div>
        </div>

        <div class="d-flex justify-content-center">
          @can('create', \App\Models\PurchaseOrder::class)
            <a href="#" id="pov-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          @endcan
          <a href="{{ route('app-po-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- FRD V01.14 §1.13 details statistics: this PO's trips and the cost used, from the quota ledger; the quantity of
         waste moved comes from trips, which don't exist yet — 0 until they do. --}}
    <div class="row g-4 mb-6">
      @foreach ([
        ['key' => 'trips',  'label' => __('Trips of this contract'), 'color' => 'success', 'icon' => 'ti-truck'],
        ['key' => 'cost',   'label' => __('Cost used'),              'color' => 'warning', 'icon' => 'ti-coin'],
        ['key' => 'waste',  'label' => __('Waste moved (ton)'),      'color' => 'info',    'icon' => 'ti-recycle'],
      ] as $c)
        <div class="col-lg-4 col-sm-6">
          <div class="card card-border-shadow-{{ $c['color'] }} h-100">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <div class="avatar me-4">
                  <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
                </div>
                <h4 class="mb-0" dir="ltr" data-stat="{{ $c['key'] }}">0</h4>
              </div>
              <p class="mb-0 text-heading fw-medium">{{ $c['label'] }}</p>
              <small class="text-muted" data-stat-sub="{{ $c['key'] }}"></small>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- "تفاصيل بنود التعاقد": one card per main service with how its quota is counted and what is left of each part. --}}
    <h5 class="mb-4">{{ __('Contract Services') }}</h5>
    <div id="pov-services"></div>
  </div>
</div>

@endsection
