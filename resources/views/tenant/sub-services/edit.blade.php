@extends('layouts/layoutMaster')

@section('title', __('Edit Service Details'))

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
@include('tenant.sub-services._translations')
<script>
  window.subServiceEditId = {{ $serviceId }};
</script>
@vite('resources/assets/js/app-sub-service-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'sub-service-last-updated', 'pageTitle' => __('Edit Service Details'), 'breadcrumbs' => [
  ['title' => __('Sub Services'), 'url' => route('app-sub-service-list')],
  ['title' => __('Edit Sub-service')],
]])

<div id="sub-service-form-error" class="alert alert-danger d-none"></div>
<div id="sub-service-status-ok" class="alert alert-success d-none"></div>

@include('tenant.sub-services._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.9.2: deactivate / re-activate lives on the edit page. --}}
<div class="card d-none" id="sub-service-status-card">
  <h5 class="card-header">{{ __('Service Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this service\'s status?') }}</h5>
      <p class="mb-0">{{ __('A deactivated sub-service is kept for its history but can\'t be chosen for new trips. It can only be reactivated while its main service is active.') }}</p>
    </div>
    <form id="subServiceStatusForm">
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
        <input class="form-check-input" type="checkbox" id="ss-confirm" />
        <label class="form-check-label" for="ss-confirm">{{ __('I confirm this status change') }}</label>
      </div>
      <button type="submit" class="btn btn-danger" id="ss-submit" disabled>{{ __('Update Status') }}</button>
    </form>
  </div>
</div>

@endsection
