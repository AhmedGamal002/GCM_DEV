@extends('layouts/layoutMaster')

@section('title', 'Add Tenant — Platform')

@section('content')

@include('_partials.breadcrumb', [
  'homeUrl' => route('platform.dashboard'),
  'breadcrumbs' => [
    ['title' => 'Tenants', 'url' => route('platform.tenants.index')],
    ['title' => 'Add'],
  ],
])

<h4 class="mb-6">Add New Tenant</h4>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('platform.tenants.store') }}">
      @csrf

      <h6 class="mb-4">Organization</h6>
      <div class="row g-6 mb-6">
        <div class="col-md-6">
          <label class="form-label" for="name">Name</label>
          <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="slug">Slug</label>
          <input type="text" id="slug" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="e.g. acme-co" required>
          <small class="text-muted">Used to identify the tenant (subdomain-ready). Letters, numbers, dashes and underscores only.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="domain">Custom Domain</label>
          <input type="text" id="domain" name="domain" class="form-control" value="{{ old('domain') }}" placeholder="Optional">
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6 class="mb-4">First User — System Admin</h6>
      <p class="text-muted">Every tenant needs exactly one System Admin. This account is created now and can invite everyone else.</p>
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="admin_name">Full Name</label>
          <input type="text" id="admin_name" name="admin_name" class="form-control" value="{{ old('admin_name') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="admin_email">Email</label>
          <input type="email" id="admin_email" name="admin_email" class="form-control" value="{{ old('admin_email') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="admin_phone">Mobile Number</label>
          <input type="tel" id="admin_phone" name="admin_phone" class="form-control" value="{{ old('admin_phone') }}" required>
        </div>
        <div class="col-md-6"></div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="admin_password">Password</label>
            <div class="input-group input-group-merge">
              <input type="password" id="admin_password" name="admin_password" class="form-control" required>
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="admin_password_confirmation">Confirm Password</label>
            <div class="input-group input-group-merge">
              <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" class="form-control" required>
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-6">
        <button type="submit" class="btn btn-primary me-4">Create Tenant</button>
        <a href="{{ route('platform.tenants.index') }}" class="btn btn-label-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
