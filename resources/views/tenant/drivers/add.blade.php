@extends('layouts/layoutMaster')

@section('title', __('Create New Driver'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/quill/typography.scss',
  'resources/assets/vendor/libs/quill/editor.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/quill/quill.js'
])
@endsection

@section('page-script')
<script>
  window.driverAddTranslations = {
    active: @json(__('Active')),
    on_vacation: @json(__('On Vacation')),
    deactivated: @json(__('Deactivated')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    add_permit: @json(__('Add Permit')),
    remove: @json(__('Remove')),
    loading: @json(__('Loading...')),
    select_types_first: @json(__('Select vehicle type(s) first')),
    select_vehicle: @json(__('Select a vehicle'))
  };
</script>
@vite('resources/assets/js/app-driver-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Driver'), 'breadcrumbs' => [
  ['title' => __('Drivers'), 'url' => route('app-driver-list')],
  ['title' => __('Add')],
]])

<div id="driver-add-error" class="alert alert-danger d-none"></div>

<form id="driverAddForm">

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
        <div class="col-12">
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
    <h5 class="card-header">{{ __('Default Vehicle') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label d-block">{{ __('Vehicle Types Qualified to Drive') }}</label>
          <div id="vehicle-category-checkboxes" class="d-flex flex-wrap gap-4"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="default_vehicle_id">{{ __('Default Vehicle') }}</label>
          <select id="default_vehicle_id" class="form-select select2" required>
            <option value="">{{ __('Select vehicle type(s) first') }}</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Residence') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-4">
          <label class="form-label" for="residence_number">{{ __('Residence Number') }}</label>
          <input type="text" id="residence_number" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="residence_valid_to">{{ __('Valid To') }}</label>
          <input type="date" id="residence_valid_to" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="residence_attachment">{{ __('Attachment') }}</label>
          <input type="file" id="residence_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Driving License') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-4">
          <label class="form-label" for="license_number">{{ __('License Number') }}</label>
          <input type="text" id="license_number" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="license_valid_to">{{ __('Valid To') }}</label>
          <input type="date" id="license_valid_to" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="license_attachment">{{ __('Attachment') }}</label>
          <input type="file" id="license_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Operational License') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-4">
          <label class="form-label" for="operational_license_number">{{ __('License Number') }}</label>
          <input type="text" id="operational_license_number" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="operational_license_valid_to">{{ __('Valid To') }}</label>
          <input type="date" id="operational_license_valid_to" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="operational_license_attachment">{{ __('Attachment') }}</label>
          <input type="file" id="operational_license_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Driver Insurance') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-4">
          <label class="form-label" for="insurance_number">{{ __('Insurance Number') }}</label>
          <input type="text" id="insurance_number" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="insurance_valid_to">{{ __('Valid To') }}</label>
          <input type="date" id="insurance_valid_to" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="insurance_attachment">{{ __('Attachment') }}</label>
          <input type="file" id="insurance_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ __('Truck Entry Permits') }}</h5>
      <button type="button" id="add-entry-permit" class="btn btn-label-secondary btn-sm">
        <i class="ti ti-plus me-1"></i>{{ __('Add Permit') }}
      </button>
    </div>
    <div class="card-body">
      <div id="entry-permits-list"></div>
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
    <a href="{{ route('app-driver-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>

<!-- Entry permit row template -->
<template id="entry-permit-row-template">
  <div class="row g-6 align-items-end entry-permit-row mb-4 pb-4 border-bottom">
    <div class="col-md-3">
      <label class="form-label">{{ __('Area Name') }}</label>
      <input type="text" class="form-control entry-area-name" required />
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ __('Permit Number') }}</label>
      <input type="text" class="form-control entry-permit-number" required />
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ __('Valid To') }}</label>
      <input type="date" class="form-control entry-valid-to" required />
    </div>
    <div class="col-md-2">
      <label class="form-label">{{ __('Attachment') }}</label>
      <input type="file" class="form-control entry-attachment" accept=".pdf,image/*" />
    </div>
    <div class="col-md-1">
      <button type="button" class="btn btn-icon btn-text-danger remove-entry-permit"><i class="ti ti-trash"></i></button>
    </div>
  </div>
</template>

@endsection
