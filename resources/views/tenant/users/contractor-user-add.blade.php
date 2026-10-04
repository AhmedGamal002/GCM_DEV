@extends('layouts/layoutMaster')

@section('title', __('Create Contractor User'))

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
  window.contractorUserFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    deactivated: @json(__('Deactivated')),
    list_url: @json(route('app-user-list'))
  };
</script>
@vite('resources/assets/js/app-contractor-user-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create Contractor User'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Add Contractor User')],
]])

<div id="contractor-user-form-error" class="alert alert-danger d-none"></div>

@include('tenant.users._contractor-user-form', ['mode' => 'create'])

@endsection
