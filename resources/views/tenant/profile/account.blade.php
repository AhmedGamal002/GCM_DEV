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
    <div class="card mb-6">
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

    {{-- FRD V01.14 §1.4: a client account also manages its own signature and operational stamp. --}}
    @if($user->isClient())
    <div class="card mb-6" id="signature-card">
      <h5 class="card-header">{{ __('Signature & Stamp') }}</h5>
      <div class="card-body pt-1">
        <div id="signature-status" class="alert alert-success d-none"></div>
        <div id="signature-error" class="alert alert-danger d-none"></div>
        <form id="formSignatureStamp"
          data-nothing-message="{{ __('Choose a signature or stamp image first.') }}"
          data-generic-error="{{ __('Something went wrong. Please try again.') }}">
          <div class="row">
            <div class="mb-6 col-md-6">
              <label class="form-label" for="signature">{{ __('Signature image') }}</label>
              <input type="file" id="signature" class="form-control" accept="image/*" />
              @if($user->signature_image)
                <img src="{{ route('api.users.images.download', ['user' => $user->id, 'type' => 'signature']) }}" alt="" class="border rounded bg-white p-1 mt-3" style="max-width: 100%; max-height: 100px" />
              @endif
            </div>
            <div class="mb-6 col-md-6">
              <label class="form-label" for="stamp">{{ __('Operational stamp image') }}</label>
              <input type="file" id="stamp" class="form-control" accept="image/*" />
              @if($user->stamp_image)
                <img src="{{ route('api.users.images.download', ['user' => $user->id, 'type' => 'stamp']) }}" alt="" class="border rounded bg-white p-1 mt-3" style="max-width: 100%; max-height: 100px" />
              @endif
            </div>
          </div>
          <div class="text-muted small mb-4">{{ __('Allowed JPG, PNG. Max size of 2MB.') }}</div>
          <button type="submit" class="btn btn-primary">{{ __('Save signature & stamp') }}</button>
        </form>
      </div>
    </div>
    @endif

    @if($user->canChangeOwnPassword())
    <div class="card" id="password-card">
      <h5 class="card-header">{{ __('Change Password') }}</h5>
      <div class="card-body pt-1">
        <div id="password-status" class="alert alert-success d-none"></div>
        <div id="password-error" class="alert alert-danger d-none"></div>
        <form id="formPasswordChange"
          data-updated-message="{{ __('Password updated.') }}"
          data-generic-error="{{ __('Something went wrong. Please try again.') }}">
          <div class="row">
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="currentPassword">{{ __('Current Password') }}</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="currentPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="newPassword">{{ __('New Password') }}</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="newPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
            <div class="mb-6 col-md-6 form-password-toggle">
              <label class="form-label" for="confirmPassword">{{ __('Confirm New Password') }}</label>
              <div class="input-group input-group-merge">
                <input class="form-control" type="password" id="confirmPassword" required />
                <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">{{ __('Save changes') }}</button>
            <button type="reset" class="btn btn-label-secondary">{{ __('Reset') }}</button>
          </div>
        </form>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
