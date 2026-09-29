@extends('layouts/layoutMaster')

@section('title', __('Create New Facility'))

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
  window.facilityFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    prefix_format: @json(__('The prefix must be exactly 3 English letters.')),
    efficiency_range: @json(__('Enter a percentage between 0 and 100.')),
    date_order: @json(__('The end date can\'t be before the start date.')),
    list_url: @json(route('app-facility-list'))
  };
</script>
@vite('resources/assets/js/app-facility-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Facility'), 'breadcrumbs' => [
  ['title' => __('Facilities'), 'url' => route('app-facility-list')],
  ['title' => __('Create New')],
]])

<div id="facility-form-error" class="alert alert-danger d-none"></div>

@include('tenant.facilities._form', ['mode' => 'create'])

@endsection
