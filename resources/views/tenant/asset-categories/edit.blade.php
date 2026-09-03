@extends('layouts/layoutMaster')

@section('title', __('Edit category'))

@section('page-script')
<script>
  window.assetCategoryFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    list_url: @json(route('app-asset-category-list'))
  };
  window.assetCategoryEditId = {{ $categoryId }};
</script>
@vite('resources/assets/js/app-asset-category-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Asset capacity categories'), 'url' => route('app-asset-category-list')],
  ['title' => __('Edit category')],
]])

<div id="asset-category-form-error" class="alert alert-danger d-none"></div>

@include('tenant.asset-categories._form', ['mode' => 'edit'])

@endsection
