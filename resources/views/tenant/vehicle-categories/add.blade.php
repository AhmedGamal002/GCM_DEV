@extends('layouts/layoutMaster')

@section('title', __('Create Vehicle Category'))

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
<script>
  window.vehicleCategoryFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    select: @json(__('Select...')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    pick_type: @json(__('Choose the container type first.')),
    no_capacity_of_type: @json(__('No :type capacities are defined yet.')),
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
