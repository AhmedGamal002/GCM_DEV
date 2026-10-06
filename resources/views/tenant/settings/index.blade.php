@extends('layouts/layoutMaster')

@section('title', __('General Settings'))

@section('page-script')
<script>
  window.settingsTranslations = {
    saved: @json(__('Settings saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.'))
  };
</script>
@vite('resources/assets/js/app-settings.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('General Settings'), 'breadcrumbs' => [
  ['title' => __('Settings')],
]])

<div id="settings-ok" class="alert alert-success d-none"></div>
<div id="settings-error" class="alert alert-danger d-none"></div>

<div class="card mb-6" id="settings-loading">
  <div class="card-body text-center py-6">
    <div class="spinner-border" role="status"></div>
  </div>
</div>

<form id="settingsForm" class="d-none" novalidate>
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Document expiry alerts') }}</h5>
    <div class="card-body">
      <p class="text-muted">{{ __('A dated document shows a green bar with the days left. When the days left reach the number set here, the bar turns red and shrinks day by day until the date passes.') }}</p>
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="driver_expiry_warning_days">{{ __('Driver documents — warning period (days)') }}</label>
          <input type="number" id="driver_expiry_warning_days" name="driver_expiry_warning_days" class="form-control" min="1" max="365" step="1" dir="ltr" required />
          <div class="form-text">{{ __('Applies to the residence, driving license, operational license, insurance and entry permits of every driver.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="vehicle_expiry_warning_days">{{ __('Vehicle documents — warning period (days)') }}</label>
          <input type="number" id="vehicle_expiry_warning_days" name="vehicle_expiry_warning_days" class="form-control" min="1" max="365" step="1" dir="ltr" required />
          <div class="form-text">{{ __('Applies to the registration card, fitness document, inspection certificate, insurance and entry permits of every vehicle.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
  </div>
</form>

@endsection
