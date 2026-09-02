@extends('layouts/layoutMaster')

@section('title', __('Users'))

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
  window.userListTranslations = {
    no_permission: @json(__("You don't have permission to view this data.")),
    all_roles: @json(__('All roles')),
    all_statuses: @json(__('All statuses')),
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    edit: @json(__('Edit')),
    view: @json(__('View')),
    actions: @json(__('Actions')),
    add_user: @json(__('Add User')),
    add_gcm_staff: @json(__('GCM Staff (Data Entry / Auditor)')),
    add_driver: @json(__('Driver')),
    search_user: @json(__('Search User')),
    no_users_found: @json(__('No users found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('User created successfully.')),
    add_user_url: @json(route('app-user-add')),
    add_driver_url: @json(route('app-driver-add')),
    view_url_base: @json(url('/app/user/view')),
    edit_url_base: @json(url('/app/user/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-user-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('List')],
]])

<div id="user-list-status" class="alert alert-success d-none"></div>

<!-- Users List Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 user_role"></div>
      <div class="col-md-4 user_status"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-users table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th></th>
          <th>{{ __('ID') }}</th>
          <th>{{ __('User') }}</th>
          <th>{{ __('Affiliation') }}</th>
          <th>{{ __('Entity') }}</th>
          <th>{{ __('Role') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
