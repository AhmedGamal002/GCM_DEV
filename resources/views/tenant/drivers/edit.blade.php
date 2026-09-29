@extends('layouts/layoutMaster')

@section('title', __('Edit Driver'))

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
  window.driverEditTranslations = {
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    view_url_base: @json(url('/app/driver/view')),
    loading: @json(__('Loading...')),
    select_types_first: @json(__('Select vehicle type(s) first')),
    select_vehicle: @json(__('Select a vehicle')),
    last_updated_by: @json(__('Last updated by :name — :at'))
  };
  window.driverEditId = {{ $driverId }};
</script>
@vite('resources/assets/js/app-driver-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'de-updated-by', 'pageTitle' => __('Edit Account'), 'breadcrumbs' => [
  ['title' => __('Drivers'), 'url' => route('app-driver-list')],
  ['title' => __('Edit')],
]])

<div id="driver-edit-error" class="alert alert-danger d-none"></div>

<div class="card mb-6">
  <div class="card-body text-center py-6" id="driver-edit-loading">
    <div class="spinner-border" role="status"></div>
  </div>

  <div class="d-none" id="driver-edit-form-wrapper">
    <form class="card-body" id="driverEditForm">

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

      <hr class="my-6 mx-n4" />
      <h6>2. {{ __('Default Vehicle') }}</h6>
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label d-block">{{ __('Vehicle Types Qualified to Drive') }}</label>
          <div id="vehicle-category-checkboxes" class="d-flex flex-wrap gap-4"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="default_vehicle_id">{{ __('Default Vehicle') }}</label>
          <select id="default_vehicle_id" class="form-select" required>
            <option value="">{{ __('Select vehicle type(s) first') }}</option>
          </select>
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6>3. {{ __('Residence') }}</h6>
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
          <label class="form-label" for="residence_attachment">{{ __('Replace Attachment') }}</label>
          <input type="file" id="residence_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6>4. {{ __('Driving License') }}</h6>
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
          <label class="form-label" for="license_attachment">{{ __('Replace Attachment') }}</label>
          <input type="file" id="license_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6>5. {{ __('Operational License') }}</h6>
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
          <label class="form-label" for="operational_license_attachment">{{ __('Replace Attachment') }}</label>
          <input type="file" id="operational_license_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6>6. {{ __('Driver Insurance') }}</h6>
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
          <label class="form-label" for="insurance_attachment">{{ __('Replace Attachment') }}</label>
          <input type="file" id="insurance_attachment" class="form-control" accept=".pdf,image/*" />
        </div>
      </div>

      <hr class="my-6 mx-n4" />
      <h6>7. {{ __('Additional Info') }}</h6>
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label">{{ __('Additional Data') }}</label>
          <div class="comment-editor border" id="additional-data-editor"></div>
        </div>
      </div>

      <div class="pt-6">
        <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
        <a href="#" id="driver-edit-cancel" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>

@endsection
