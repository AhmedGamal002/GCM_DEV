@extends('layouts/layoutMaster')

@section('title', __('Create New User'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/quill/typography.scss',
  'resources/assets/vendor/libs/quill/editor.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/quill/quill.js'
])
@endsection

@section('page-script')
<script>
  window.userAddTranslations = {
    data_entry: @json(__('Data Entry')),
    system_auditor: @json(__('System Auditor')),
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    created: @json(__('User created successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.'))
  };
</script>
@vite('resources/assets/js/app-user-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New User'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Add')],
]])

<div id="user-add-status" class="alert alert-success d-none"></div>
<div id="user-add-error" class="alert alert-danger d-none"></div>

<form id="userAddForm">

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Account Details') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Full Name') }}</label>
          <input type="text" id="name" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">{{ __('Email') }}</label>
          <input type="email" id="email" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">{{ __('Mobile Number') }}</label>
          <input type="tel" id="phone" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="photo">{{ __('Photo') }}</label>
          <input type="file" id="photo" class="form-control" accept="image/*" />
        </div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="password">{{ __('Password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password" class="form-control" required />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password_confirmation" class="form-control" required />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Role & Status') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6" id="job-role-wrapper">
          <label class="form-label d-block">{{ __('Job Role') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="role" id="role-data-entry" value="data_entry" checked>
            <label class="form-check-label" for="role-data-entry">{{ __('Data Entry') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="role" id="role-auditor" value="auditor">
            <label class="form-check-label" for="role-auditor">{{ __('System Auditor') }}</label>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Account Status') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-active" value="active" checked>
            <label class="form-check-label" for="status-active">{{ __('Active') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-vacation" value="on_vacation">
            <label class="form-check-label" for="status-vacation">{{ __('On Vacation') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-deactivated" value="deactivated">
            <label class="form-check-label" for="status-deactivated">{{ __('Deactivated') }}</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Additional Info') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label">{{ __('Additional Data') }}</label>
          <div class="comment-editor border" id="additional-data-editor"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
@endsection
