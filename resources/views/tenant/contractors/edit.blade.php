@extends('layouts/layoutMaster')

@section('title', __('Edit Contractor'))

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
  window.contractorFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    prefix_format: @json(__('The short name must be exactly 3 English letters.')),
    prefix_taken: @json(__('This short name is already in use.')),
    digits_only: @json(__('The number must contain digits only.')),
    no_services: @json(__('No main services are available yet.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    current_file: @json(__('Current file')),
    keep_file_hint: @json(__('Leave empty to keep the current file.')),
    status_updated: @json(__('Contractor status updated successfully.')),
    list_url: @json(route('app-contractor-list')),
    view_url_base: @json(url('/app/contractor/view'))
  };
  window.contractorEditId = {{ $contractorId }};
</script>
@vite('resources/assets/js/app-contractor-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'contractor-last-updated', 'pageTitle' => __('Edit Contractor Details'), 'breadcrumbs' => [
  ['title' => __('Contractors'), 'url' => route('app-contractor-list')],
  ['title' => __('Edit Contractor')],
]])

<div id="contractor-form-error" class="alert alert-danger d-none"></div>
<div id="contractor-status-ok" class="alert alert-success d-none"></div>

@include('tenant.contractors._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.10.4: deactivate / re-activate lives on the edit page. --}}
<div class="card d-none mt-6" id="contractor-status-card">
  <h5 class="card-header">{{ __('Contractor Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this contractor\'s status?') }}</h5>
      <p class="mb-0">{{ __('A deactivated contractor is kept for its history but is no longer offered for new work. Its drivers, vehicles and assets are not changed.') }}</p>
    </div>
    <form id="contractorStatusForm">
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
        <input class="form-check-input" type="checkbox" id="cs-confirm" />
        <label class="form-check-label" for="cs-confirm">{{ __('I confirm this status change') }}</label>
      </div>
      <button type="submit" class="btn btn-danger" id="cs-submit" disabled>{{ __('Update Status') }}</button>
    </form>
  </div>
</div>

@endsection
