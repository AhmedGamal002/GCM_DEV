@extends('layouts/layoutMaster')

@section('title', __('Create Asset Capacity'))

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
<script>
  window.assetCategoryFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    list_url: @json(route('app-asset-category-list'))
  };
</script>
@vite('resources/assets/js/app-asset-category-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create Asset Capacity'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Assets Capacities'), 'url' => route('app-asset-category-list')],
  ['title' => __('Create Asset Capacity')],
]])

<div id="asset-category-form-error" class="alert alert-danger d-none"></div>

@include('tenant.asset-categories._form', ['mode' => 'create'])

@endsection
