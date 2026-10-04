@extends('layouts/layoutMaster')

@section('title', __('Contractor Details'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/scss/pages/page-user-view.scss'
])
@endsection

@section('page-script')
<script>
  window.contractorViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    saved: @json(__('Changes saved successfully.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    download: @json(__('Download')),
    period: @json(__(':from to :to')),
    all_services: @json(__('All services')),
    edit_url_base: @json(url('/app/contractor/edit')),
    can_manage: @json(auth()->user()->can('create', \App\Models\Contractor::class))
  };
  window.contractorViewId = {{ $contractorId }};
</script>
@vite('resources/assets/js/app-contractor-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'cv-updated-by', 'pageTitle' => __('Contractor Details'), 'breadcrumbs' => [
  ['title' => __('Contractors'), 'url' => route('app-contractor-list')],
  ['title' => __('Contractor Details')],
]])

<div id="contractor-view-status" class="alert alert-success d-none"></div>
<div id="contractor-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="contractor-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="contractor-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <img id="cv-logo" class="rounded d-none" alt="" style="object-fit: cover" />
            <span class="avatar-initial rounded bg-label-primary" id="cv-logo-fallback"><i class="ti ti-building-community ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="cv-name"></h5>
          <span class="text-muted mb-2" id="cv-trading-name"></span>
          <span class="badge" id="cv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('ID') }}:</span> <span id="cv-code" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Short name') }}:</span> <span id="cv-prefix" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Phone') }}:</span> <span id="cv-phone" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Address') }}:</span> <span id="cv-address"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Location (map link)') }}:</span> <span id="cv-location">—</span></li>
        </ul>

        <h5 class="pb-4 border-bottom mb-4">{{ __('Certified services') }}</h5>
        <div class="mb-6" id="cv-services"></div>

        <h5 class="pb-4 border-bottom mb-4">{{ __('Contract') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Contract No.') }}:</span> <span id="cv-contract-number" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Contract period') }}:</span> <span id="cv-contract-period"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Contract copy') }}:</span> <span id="cv-contract-file">—</span></li>
          <li class="mb-2"><span class="h6">{{ __('Commercial registration No.') }}:</span> <span id="cv-cr-number" dir="ltr"></span> <span id="cv-cr-file"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Tax registration No.') }}:</span> <span id="cv-tax-number" dir="ltr"></span> <span id="cv-tax-file"></span></li>
        </ul>

        <div id="cv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="cv-additional"></div>
        </div>

        <div class="d-flex justify-content-center">
          <a href="#" id="cv-edit-link" class="btn btn-primary me-4 d-none">{{ __('Edit') }}</a>
          <a href="{{ route('app-contractor-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- The resources this contractor supplies (drivers, vehicles, containers, tanks). --}}
    <div class="row g-4 mb-6">
      @foreach ([
        ['key' => 'drivers_count',    'label' => __('Drivers'),    'color' => 'warning', 'icon' => 'ti-steering-wheel'],
        ['key' => 'vehicles_count',   'label' => __('Vehicles'),   'color' => 'info',    'icon' => 'ti-truck'],
        ['key' => 'containers_count', 'label' => __('Containers'), 'color' => 'success', 'icon' => 'ti-box'],
        ['key' => 'tanks_count',      'label' => __('Tanks'),      'color' => 'primary', 'icon' => 'ti-droplet'],
      ] as $c)
        <div class="col-lg-3 col-sm-6">
          <div class="card card-border-shadow-{{ $c['color'] }} h-100">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <div class="avatar me-4">
                  <span class="avatar-initial rounded bg-label-{{ $c['color'] }}"><i class="ti {{ $c['icon'] }} ti-28px"></i></span>
                </div>
                <h4 class="mb-0" data-stat="{{ $c['key'] }}">0</h4>
              </div>
              <p class="mb-0 text-heading fw-medium">{{ $c['label'] }}</p>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

@endsection
