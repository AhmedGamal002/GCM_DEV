@extends('layouts/layoutMaster')

@section('title', 'My Profile')

@section('page-script')
@vite(['resources/assets/js/pages-account-settings-account.js'])
@endsection

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-2 gap-lg-0">
        <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="ti-sm ti ti-users me-1_5"></i> Account</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-security') }}"><i class="ti-sm ti ti-lock me-1_5"></i> Security</a></li>
      </ul>
    </div>
    <div class="card">
      <div class="card-body">
        <div id="account-status" class="alert alert-success d-none"></div>
        <div id="account-error" class="alert alert-danger d-none"></div>
        <form id="formAccountSettings">
          <div class="row">
            <div class="mb-4 col-md-6">
              <label for="name" class="form-label">Name</label>
              <input class="form-control" type="text" id="name" value="{{ $user->name }}" required autofocus />
            </div>
            <div class="mb-4 col-md-6">
              <label for="email" class="form-label">E-mail</label>
              <input class="form-control" type="text" id="email" value="{{ $user->email }}" disabled />
              <small class="text-muted">Email cannot be changed.</small>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
