@extends('layouts/layoutMaster')

@section('title', __('Edit Client Company'))

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
  window.companyFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    prefix_format: @json(__('The short name must be exactly 3 English letters.')),
    digits_only: @json(__('The number must contain digits only.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    current_file: @json(__('Current file')),
    keep_file_hint: @json(__('Leave empty to keep the current file.')),
    status_updated: @json(__('Client company status updated successfully.')),
    list_url: @json(route('app-company-list')),
    view_url_base: @json(url('/app/company/view'))
  };
  window.companyEditId = {{ $companyId }};
</script>
@vite('resources/assets/js/app-company-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'company-last-updated', 'pageTitle' => __('Edit Client Company Details'), 'breadcrumbs' => [
  ['title' => __('Client Companies'), 'url' => route('app-company-list')],
  ['title' => __('Edit Client Company')],
]])

<div id="company-form-error" class="alert alert-danger d-none"></div>
<div id="company-status-ok" class="alert alert-success d-none"></div>

@include('tenant.companies._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.11.4: deactivate / re-activate lives on the edit page. --}}
<div class="card d-none" id="company-status-card">
  <h5 class="card-header">{{ __('Company Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this company\'s status?') }}</h5>
      <p class="mb-0">{{ __('A deactivated company is kept for its history but is no longer offered for new work.') }}</p>
    </div>
    <form id="companyStatusForm">
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
