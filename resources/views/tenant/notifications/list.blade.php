@extends('layouts/layoutMaster')

@section('title', __('Notifications'))

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
  window.notificationListTranslations = {
    types: @json($types),
    all_types: @json(__('All types')),
    search: @json(__('Search notifications')),
    no_notifications: @json(__('No notifications yet.')),
    no_permission: @json(__("You don't have permission to view this data.")),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    new_label: @json(__('New'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-notification-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Notifications'), 'breadcrumbs' => [
  ['title' => __('Notifications')],
]])

<div class="card">
  <div class="card-header border-bottom">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
      <button type="button" class="btn btn-label-secondary btn-sm" id="notifications-read-all"><i class="ti ti-checks me-1"></i>{{ __('Mark all as read') }}</button>
    </div>
    <div class="row pt-4 gap-y-4 g-4">
      <div class="col-md-4 notification_type"></div>
      <div class="col-md-4"><input type="date" class="form-control" id="notification-date-from" aria-label="{{ __('Duration from') }}" title="{{ __('Duration from') }}"></div>
      <div class="col-md-4"><input type="date" class="form-control" id="notification-date-to" aria-label="{{ __('Duration to') }}" title="{{ __('Duration to') }}"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-notifications table">
      <thead class="border-top">
        <tr>
          <th>{{ __('Date & Time') }}</th>
          <th>{{ __('Notification type') }}</th>
          <th>{{ __('Notification details') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
