@extends('layouts/layoutMaster')

@section('title', __('Client Companies'))

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
  window.companyListTranslations = {
    all_statuses: @json(__('All statuses')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_company: @json(__('Add Client Company')),
    search_company: @json(__('Search Client Company')),
    no_companies_found: @json(__('No client companies found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Client company created successfully.')),
    add_company_url: @json(route('app-company-add')),
    view_url_base: @json(url('/app/company/view')),
    edit_url_base: @json(url('/app/company/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-company-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Client Companies'), 'breadcrumbs' => [
  ['title' => __('Client Companies'), 'url' => route('app-company-list')],
  ['title' => __('List')],
]])

<div id="company-list-status" class="alert alert-success d-none"></div>

<!-- Companies List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 company_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-companies table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Company') }}</th>
          <th>{{ __('Company representative') }}</th>
          <th>{{ __('Projects') }}</th>
          <th>{{ __('Users') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
