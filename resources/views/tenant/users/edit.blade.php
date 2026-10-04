@extends('layouts/layoutMaster')

@section('title', __('Edit Account'))

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
  window.userEditTranslations = {
    data_entry: @json(__('Data Entry')),
    system_auditor: @json(__('System Auditor')),
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    view_url_base: @json(url('/app/user/view')),
    driver_edit_url_base: @json(url('/app/driver/edit')),
    client_edit_url_base: @json(url('/app/client-user/edit')),
    last_updated_by: @json(__('Last updated by :name — :at'))
  };
  window.userEditId = {{ $userId }};
</script>
@vite('resources/assets/js/app-user-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'ue-updated-by', 'pageTitle' => __('Edit Account'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Edit')],
]])

<div id="user-edit-status" class="alert alert-success d-none"></div>
<div id="user-edit-error" class="alert alert-danger d-none"></div>

<div class="card mb-6" id="user-edit-loading">
  <div class="card-body text-center py-6">
    <div class="spinner-border" role="status"></div>
  </div>
</div>

<div class="card mb-6 d-none" id="user-edit-driver-redirect">
  <div class="card-body">
    <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
      <i class="ti ti-info-circle me-2"></i>
      <span>
        {{ __('This account is a Driver — edit it from the') }}
        <a href="#" id="user-edit-driver-link">{{ __('Drivers') }}</a>
        {{ __('page instead, where residence/license/insurance details are also editable.') }}
      </span>
    </div>
  </div>
</div>

<div class="card mb-6 d-none" id="user-edit-client-redirect">
  <div class="card-body">
    <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
      <i class="ti ti-info-circle me-2"></i>
      <span>
        {{ __('This account belongs to a client company — edit it from the') }}
        <a href="#" id="user-edit-client-link">{{ __('client account form') }}</a>
        {{ __('instead, where its company and project access are also editable.') }}
      </span>
    </div>
  </div>
</div>

<div class="d-none" id="user-edit-form-wrapper">
  <form id="userEditForm">

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
            <input type="email" id="email" class="form-control" disabled />
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
              <label class="form-label" for="password">{{ __('New Password') }}</label>
              <div class="input-group input-group-merge">
                <input type="password" id="password" class="form-control" autocomplete="new-password" />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
              <small class="text-muted">{{ __('Leave blank to keep the current password.') }}</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-password-toggle">
              <label class="form-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
              <div class="input-group input-group-merge">
                <input type="password" id="password_confirmation" class="form-control" autocomplete="new-password" />
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
              <input class="form-check-input" type="radio" name="role" id="role-data-entry" value="data_entry">
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
              <input class="form-check-input" type="radio" name="status" id="status-active" value="active">
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
      <a href="#" id="user-edit-cancel" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>

@endsection
