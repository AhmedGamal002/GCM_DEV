@extends('layouts/layoutMaster')

@section('title', __('Client Company Details'))

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
  window.companyViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    download: @json(__('Download')),
    period: @json(__(':from to :to')),
    all_statuses: @json(__('All statuses')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    export: @json(__('Export')),
    search_project: @json(__('Search Project')),
    no_projects_found: @json(__('This company has no projects yet.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    can_manage: @json(auth()->user()->can('create', \App\Models\Project::class)),
    add_project: @json(__('Add Project')),
    add_project_url: @json(route('app-project-add')),
    project_view_url_base: @json(url('/app/project/view')),
    edit_url_base: @json(url('/app/company/edit'))
  };
  window.companyViewId = {{ $companyId }};
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-company-view.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'cv-updated-by', 'pageTitle' => __('Client Company Details'), 'breadcrumbs' => [
  ['title' => __('Client Companies'), 'url' => route('app-company-list')],
  ['title' => __('Client Company Details')],
]])

<div id="company-view-status" class="alert alert-success d-none"></div>
<div id="company-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="company-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="company-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <img id="cv-logo" class="rounded d-none" alt="" style="object-fit: cover" />
            <span class="avatar-initial rounded bg-label-primary" id="cv-logo-fallback"><i class="ti ti-building ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="cv-name"></h5>
          <span class="badge" id="cv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('ID') }}:</span> <span id="cv-code"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Short name') }}:</span> <span id="cv-prefix" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Business sector') }}:</span> <span id="cv-sector"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Phone') }}:</span> <span id="cv-phone" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Email') }}:</span> <span id="cv-email" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Address') }}:</span> <span id="cv-address"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Location (map link)') }}:</span> <span id="cv-location">—</span></li>
        </ul>

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
          <a href="#" id="cv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-company-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    {{-- FRD V01.14 §1.11.3 statistics. Projects are real; contracts, trips
         and quantities come from modules that don't exist yet — 0 until then. --}}
    <div class="row g-4 mb-6">
      @foreach ([
        ['key' => 'projects',   'label' => __('Projects'),         'color' => 'primary', 'icon' => 'ti-briefcase'],
        ['key' => 'contracts',  'label' => __('Contracts'),        'color' => 'info',    'icon' => 'ti-file-invoice'],
        ['key' => 'trips',      'label' => __('Trips'),            'color' => 'success', 'icon' => 'ti-truck'],
        ['key' => 'waste_tons', 'label' => __('Waste moved (ton)'), 'color' => 'warning', 'icon' => 'ti-recycle'],
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

    <div class="card mb-6">
      <h5 class="card-header">{{ __('Projects') }}</h5>
      <div class="card-header border-top pt-4 pb-0">
        <div class="row">
          <div class="col-md-4 company_project_status"></div>
        </div>
      </div>
      <div class="card-datatable table-responsive">
        <table class="datatables-company-projects table">
          <thead class="border-top">
            <tr>
              <th>{{ __('ID') }}</th>
              <th>{{ __('Project') }}</th>
              <th>{{ __('Contracts') }}</th>
              <th>{{ __('Users') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>

    <div class="card">
      <h5 class="card-header">{{ __('Contracts') }}</h5>
      <div class="card-body">
        <div class="alert alert-info mb-0">
          {{ __('This company\'s purchase orders will appear here once the Contracts module is available.') }}
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
