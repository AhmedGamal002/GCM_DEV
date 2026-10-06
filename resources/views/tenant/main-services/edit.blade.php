@extends('layouts/layoutMaster')

@section('title', __('Edit Main Service Details'))

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
  window.mainServiceFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    choose_one_category: @json(__('Choose at least one vehicle category.')),
    no_vehicle_categories: @json(__('No vehicle categories found.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    status_updated: @json(__('Main service status updated successfully.')),
    view_url_base: @json(url('/app/main-service/view'))
  };
  window.mainServiceEditId = {{ $serviceId }};
</script>
@vite('resources/assets/js/app-main-service-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'main-service-last-updated', 'pageTitle' => __('Edit Main Service Details'), 'breadcrumbs' => [
  ['title' => __('Main Services'), 'url' => route('app-main-service-list')],
  ['title' => __('Edit Main Service')],
]])

<div id="main-service-form-error" class="alert alert-danger d-none"></div>
<div id="main-service-status-ok" class="alert alert-success d-none"></div>

@include('tenant.main-services._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.9.1: deactivate / re-activate lives on the edit page. --}}
<div class="card d-none mt-6" id="main-service-status-card">
  <h5 class="card-header">{{ __('Service Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this service\'s status?') }}</h5>
      <p class="mb-0">{{ __('Deactivating a main service also deactivates every sub-service under it. Reactivating it does not reactivate them — each sub-service is reactivated on its own.') }}</p>
    </div>
    <form id="mainServiceStatusForm">
      <div class="mb-6">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="new_status" id="ns-active" value="active">
          <label class="form-check-label" for="ns-active">{{ __('Active') }}</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="new_status" id="ns-deactivated" value="deactivated">
          <label class="form-check-label" for="ns-deactivated">{{ __('Deactivated') }}</label>
        </div>
      </div>
      <div class="form-check my-8">
        <input class="form-check-input" type="checkbox" id="ms-confirm" />
        <label class="form-check-label" for="ms-confirm">{{ __('I confirm this status change') }}</label>
      </div>
      <button type="submit" class="btn btn-danger" id="ms-submit" disabled>{{ __('Update Status') }}</button>
    </form>
  </div>
</div>

@endsection
