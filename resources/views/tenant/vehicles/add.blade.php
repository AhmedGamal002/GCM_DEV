@extends('layouts/layoutMaster')

@section('title', __('Create New Vehicle'))

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
  window.vehicleFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    remove: @json(__('Remove')),
    required: @json(__('This field is required.')),
    numeric: @json(__('Only digits are allowed.')),
    select: @json(__('Select...')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    pick_category_and_type: @json(__('Choose the vehicle category and container type first.')),
    no_compatible_capacity: @json(__('There is no compatible :type capacity recorded for the selected vehicle category.')),
    list_url: @json(route('app-vehicle-list'))
  };
</script>
@vite('resources/assets/js/app-vehicle-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Vehicle'), 'breadcrumbs' => [
  ['title' => __('Vehicles'), 'url' => route('app-vehicle-list')],
  ['title' => __('Add')],
]])

<div id="vehicle-form-error" class="alert alert-danger d-none"></div>

@include('tenant.vehicles._form', ['mode' => 'create'])

@endsection
