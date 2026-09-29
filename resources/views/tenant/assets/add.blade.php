@extends('layouts/layoutMaster')

@section('title', __('Create New Asset'))

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
  window.assetFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    pick_one: @json(__('Select at least one option.')),
    list_url: @json(route('app-asset-list'))
  };
</script>
@vite('resources/assets/js/app-asset-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Asset'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Create New Asset')],
]])

<div id="asset-form-error" class="alert alert-danger d-none"></div>

@include('tenant.assets._form', ['mode' => 'create'])

@endsection
