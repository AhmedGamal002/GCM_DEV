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
    driver: @json(__('Driver')),
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    view_url_base: @json(url('/app/user/view'))
  };
  window.userEditId = {{ $userId }};
</script>
@vite('resources/assets/js/app-user-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Edit')],
]])

<div id="user-edit-status" class="alert alert-success d-none"></div>
<div id="user-edit-error" class="alert alert-danger d-none"></div>

<div class="card mb-6">
  <div class="card-body text-center py-6" id="user-edit-loading">
    <div class="spinner-border" role="status"></div>
  </div>

  <div class="d-none" id="user-edit-form-wrapper">
    <h5 class="card-header">{{ __('Edit Account') }}</h5>
    <form class="card-body" id="userEditForm">

      <h6>1. {{ __('Account Details') }}</h6>
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
      </div>

      <hr class="my-6 mx-n4" />
      <h6>2. {{ __('Role & Status') }}</h6>
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
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="role" id="role-driver" value="driver">
            <label class="form-check-label" for="role-driver">{{ __('Driver') }}</label>
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

      <hr class="my-6 mx-n4" />
      <h6>3. {{ __('Additional Info') }}</h6>
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label">{{ __('Additional Data') }}</label>
          <div class="comment-editor border" id="additional-data-editor"></div>
        </div>
      </div>

      <div class="pt-6">
        <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
        <a href="#" id="user-edit-cancel" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>

@endsection
