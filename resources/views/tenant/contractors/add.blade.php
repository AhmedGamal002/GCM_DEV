@extends('layouts/layoutMaster')

@section('title', __('Create New Contractor'))

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
    list_url: @json(route('app-contractor-list'))
  };
</script>
@vite('resources/assets/js/app-contractor-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Contractor'), 'breadcrumbs' => [
  ['title' => __('Contractors'), 'url' => route('app-contractor-list')],
  ['title' => __('Create New Contractor')],
]])

<div id="contractor-form-error" class="alert alert-danger d-none"></div>

@include('tenant.contractors._form', ['mode' => 'create'])

@endsection
