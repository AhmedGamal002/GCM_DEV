@extends('layouts/layoutMaster')

@section('title', __('Create Vehicle Category'))

@section('page-script')
<script>
  window.vehicleCategoryFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    list_url: @json(route('app-vehicle-category-list'))
  };
</script>
@vite('resources/assets/js/app-vehicle-category-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create Vehicle Category'), 'breadcrumbs' => [
  ['title' => __('Vehicles'), 'url' => route('app-vehicle-list')],
  ['title' => __('Categories'), 'url' => route('app-vehicle-category-list')],
  ['title' => __('Add')],
]])

<div id="vehicle-category-form-error" class="alert alert-danger d-none"></div>

@include('tenant.vehicle-categories._form', ['mode' => 'create'])

@endsection
