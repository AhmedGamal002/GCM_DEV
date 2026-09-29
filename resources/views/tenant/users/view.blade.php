@extends('layouts/layoutMaster')

@section('title', __('Account Details'))

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('page-script')
<script>
  window.userViewTranslations = {
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    edit: @json(__('Edit')),
    status_updated: @json(__('Account status updated successfully.')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    edit_url_base: @json(url('/app/user/edit')),
    last_updated_by: @json(__('Last updated by :name — :at'))
  };
  window.userViewId = {{ $userId }};
</script>
@vite('resources/assets/js/app-user-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'uv-updated-by', 'pageTitle' => __('Account Details'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('View')],
]])

<div id="user-view-status" class="alert alert-success d-none"></div>
<div id="user-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="user-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="user-view-content">
        <div class="d-flex align-items-center flex-column">
          <img id="uv-photo" class="rounded-circle mb-4" height="120" width="120" style="object-fit: cover" alt="Avatar" />
          <h5 class="mb-1" id="uv-name"></h5>
          <span class="badge" id="uv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('ID') }}:</span> <span id="uv-code"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Email') }}:</span> <span id="uv-email"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Mobile Number') }}:</span> <span id="uv-phone"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Affiliation') }}:</span> <span id="uv-affiliation"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Entity') }}:</span> <span id="uv-entity"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Job Role') }}:</span> <span id="uv-role"></span></li>
        </ul>
        <div id="uv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="uv-additional"></div>
        </div>
        <p class="small text-muted mb-4" id="uv-updated-by"></p>
        <div class="d-flex justify-content-center">
          <a href="#" id="uv-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card d-none" id="user-status-card">
      <h5 class="card-header">{{ __('Account Status') }}</h5>
      <div class="card-body">
        <div class="alert alert-warning">
          <h5 class="alert-heading mb-1">{{ __('Are you sure you want to change this account\'s status?') }}</h5>
          <p class="mb-0">{{ __("Changing the account status affects the user's ability to log in and interact with the system.") }}</p>
        </div>
        <form id="userStatusForm">
          <div class="mb-6">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-active" value="active">
              <label class="form-check-label" for="new-status-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-vacation" value="on_vacation">
              <label class="form-check-label" for="new-status-vacation">{{ __('On Vacation') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="new-status-deactivated" value="deactivated">
              <label class="form-check-label" for="new-status-deactivated">{{ __('Deactivated') }}</label>
            </div>
          </div>
          <div class="form-check my-8">
            <input class="form-check-input" type="checkbox" id="us-confirm" />
            <label class="form-check-label" for="us-confirm">{{ __('I confirm this status change') }}</label>
          </div>
          <button type="submit" class="btn btn-danger" id="us-submit" disabled>{{ __('Update Status') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
