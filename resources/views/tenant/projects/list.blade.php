@extends('layouts/layoutMaster')

@section('title', __('Client Projects'))

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
  window.projectListTranslations = {
    all_companies: @json(__('All companies')),
    all_statuses: @json(__('All statuses')),
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    view: @json(__('View')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_project: @json(__('Add Project')),
    search_project: @json(__('Search Project')),
    no_projects_found: @json(__('No projects found.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Project created successfully.')),
    can_manage: @json(auth()->user()->can('create', \App\Models\Project::class)),
    add_project_url: @json(route('app-project-add')),
    view_url_base: @json(url('/app/project/view')),
    edit_url_base: @json(url('/app/project/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-project-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Client Projects'), 'breadcrumbs' => [
  ['title' => __('Client Projects'), 'url' => route('app-project-list')],
  ['title' => __('List')],
]])

<div id="project-list-status" class="alert alert-success d-none"></div>

<!-- Projects List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 project_company"></div>
      <div class="col-md-4 project_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-projects table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('Company') }}</th>
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

@endsection
