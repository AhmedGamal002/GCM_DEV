@extends('layouts/layoutMaster')

@section('title', __('Create New Project'))

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
  window.projectFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    select_company: @json(__('Select company')),
    list_url: @json(route('app-project-list'))
  };
</script>
@vite('resources/assets/js/app-project-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Project'), 'breadcrumbs' => [
  ['title' => __('Client Projects'), 'url' => route('app-project-list')],
  ['title' => __('Create New Project')],
]])

<div id="project-form-error" class="alert alert-danger d-none"></div>

@include('tenant.projects._form', ['mode' => 'create'])

@endsection
