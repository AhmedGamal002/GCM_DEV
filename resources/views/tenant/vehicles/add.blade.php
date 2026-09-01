@extends('layouts/layoutMaster')

@section('title', __('Create New Vehicle'))

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
  window.vehicleFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    remove: @json(__('Remove')),
    required: @json(__('This field is required.')),
    numeric: @json(__('Only digits are allowed.')),
    list_url: @json(route('app-vehicle-list'))
  };
</script>
@vite('resources/assets/js/app-vehicle-add.js')
@endsection

@section('content')

<div id="vehicle-form-error" class="alert alert-danger d-none"></div>

@include('tenant.vehicles._form', ['mode' => 'create'])

@endsection
