@extends('layouts/layoutMaster')

@section('title', __('Create New Client Company'))

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
  window.companyFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    prefix_format: @json(__('The short name must be exactly 3 English letters.')),
    digits_only: @json(__('The number must contain digits only.')),
    list_url: @json(route('app-company-list'))
  };
</script>
@vite('resources/assets/js/app-company-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Client Company'), 'breadcrumbs' => [
  ['title' => __('Client Companies'), 'url' => route('app-company-list')],
  ['title' => __('Create New Client Company')],
]])

<div id="company-form-error" class="alert alert-danger d-none"></div>

@include('tenant.companies._form', ['mode' => 'create'])

@endsection
