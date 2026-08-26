@extends('layouts/layoutMaster')

@section('title', 'Change Password')

@section('page-script')
@vite(['resources/assets/js/pages-account-settings-security.js'])
@endsection

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-2 gap-lg-0">
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-account') }}"><i class="ti-sm ti ti-users me-1_5"></i> Account</a></li>
        <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="ti-sm ti ti-lock me-1_5"></i> Security</a></li>
      </ul>
    </div>
    <div class="card">
      <h5 class="card-header">Change Password</h5>
      <div class="card-body pt-1">
        <div id="password-status" class="alert alert-success d-none"></div>
        <div id="password-error" class="alert alert-danger d-none"></div>
        <form id="formPasswordChange">
          <div class="row">
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="currentPassword">Current Password</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="currentPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="newPassword">New Password</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="newPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="confirmPassword">Confirm New Password</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="confirmPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Save changes</button>
            <button type="reset" class="btn btn-label-secondary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
