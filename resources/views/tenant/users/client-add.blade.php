@extends('layouts/layoutMaster')

@section('title', __('Create Client Account'))

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
  window.clientUserFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    select_company: @json(__('Select company')),
    select_company_first: @json(__('Select a company first.')),
    no_projects: @json(__('This company has no projects yet.')),
    deactivated: @json(__('Deactivated')),
    list_url: @json(route('app-user-list'))
  };
</script>
@vite('resources/assets/js/app-client-user-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create Client Account'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Add Client Account')],
]])

<div id="client-user-form-error" class="alert alert-danger d-none"></div>

@include('tenant.users._client-form', ['mode' => 'create'])

@endsection
