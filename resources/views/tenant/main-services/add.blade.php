@extends('layouts/layoutMaster')

@section('title', __('Create New Main Service'))

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
    view_url_base: @json(url('/app/main-service/view'))
  };
</script>
@vite('resources/assets/js/app-main-service-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Main Service'), 'breadcrumbs' => [
  ['title' => __('Main Services'), 'url' => route('app-main-service-list')],
  ['title' => __('Create New')],
]])

<div id="main-service-form-error" class="alert alert-danger d-none"></div>

@include('tenant.main-services._form', ['mode' => 'create'])

@endsection
