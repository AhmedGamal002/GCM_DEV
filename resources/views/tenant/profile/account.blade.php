@extends('layouts/layoutMaster')

@section('title', __('My Profile'))

@section('page-script')
@vite(['resources/assets/js/pages-account-settings-account.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('My Profile')],
]])

<div class="row">
  <div class="col-md-12">
    @if($user->canChangeOwnPassword())
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-6 gap-2 gap-lg-0">
        <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="ti-sm ti ti-users me-1_5"></i> {{ __('Account') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-security') }}"><i class="ti-sm ti ti-lock me-1_5"></i> {{ __('Security') }}</a></li>
      </ul>
    </div>
    @endif
    <div class="card">
      <div class="card-body">
        <div id="account-status" class="alert alert-success d-none"></div>
        <div id="account-error" class="alert alert-danger d-none"></div>
        <form id="formAccountSettings"
          data-no-photo-message="{{ __('Choose a photo first.') }}"
          data-generic-error="{{ __('Something went wrong. Please try again.') }}">
          <div class="d-flex align-items-center mb-6">
            <img id="account-photo-preview" src="{{ $user->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->photo) : asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle me-4" height="100" width="100" style="object-fit: cover" />
            <div>
              <label for="photo" class="btn btn-primary me-2 mb-0">
                {{ __('Upload new photo') }}
                <input type="file" id="photo" accept="image/*" class="d-none" />
              </label>
              <div class="text-muted small mt-2">{{ __('Allowed JPG, PNG. Max size of 2MB.') }}</div>
            </div>
          </div>
          <div class="row">
            <div class="mb-4 col-md-6">
              <label for="name" class="form-label">{{ __('Full Name') }}</label>
              <input class="form-control" type="text" id="name" value="{{ $user->name }}" disabled />
            </div>
            <div class="mb-4 col-md-6">
              <label for="email" class="form-label">{{ __('Email') }}</label>
              <input class="form-control" type="text" id="email" value="{{ $user->email }}" disabled />
            </div>
          </div>
          <p class="text-muted small">{{ __('Your name and account details are managed by your administrator — only your photo can be changed here.') }}</p>
          <button type="submit" class="btn btn-primary">{{ __('Save photo') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
