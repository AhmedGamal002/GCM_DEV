@extends('layouts/layoutMaster')

@section('title', __('Edit Project'))

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
  window.projectFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    status_updated: @json(__('Project status updated successfully.')),
    list_url: @json(route('app-project-list')),
    view_url_base: @json(url('/app/project/view'))
  };
  window.projectEditId = {{ $projectId }};
</script>
@vite('resources/assets/js/app-project-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'project-last-updated', 'pageTitle' => __('Edit Project Details'), 'breadcrumbs' => [
  ['title' => __('Client Projects'), 'url' => route('app-project-list')],
  ['title' => __('Edit Project')],
]])

<div id="project-form-error" class="alert alert-danger d-none"></div>
<div id="project-status-ok" class="alert alert-success d-none"></div>

@include('tenant.projects._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.12.4: deactivate / re-activate lives on the edit page. --}}
<div class="card d-none" id="project-status-card">
  <h5 class="card-header">{{ __('Project Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this project\'s status?') }}</h5>
      <p class="mb-0">{{ __('A deactivated project is kept for its history but is no longer offered for new work.') }}</p>
    </div>
    <form id="projectStatusForm">
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
        <input class="form-check-input" type="checkbox" id="ps-confirm" />
        <label class="form-check-label" for="ps-confirm">{{ __('I confirm this status change') }}</label>
      </div>
      <button type="submit" class="btn btn-danger" id="ps-submit" disabled>{{ __('Update Status') }}</button>
    </form>
  </div>
</div>

@endsection
