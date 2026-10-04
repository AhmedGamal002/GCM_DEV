@extends('layouts/layoutMaster')

@section('title', __('Edit Contractor User'))

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
    cancel: @json(__('Cancel')),
    current_file: @json(__('Current file')),
    keep_file_hint: @json(__('Leave empty to keep the current file.')),
    not_a_contractor_user: @json(__('This account is not a contractor user — edit it from the Users page instead.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    list_url: @json(route('app-user-list')),
    view_url_base: @json(url('/app/user/view'))
  };
  window.contractorUserEditId = {{ $userId }};
</script>
@vite('resources/assets/js/app-contractor-user-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'contractor-user-last-updated', 'pageTitle' => __('Edit Account'), 'breadcrumbs' => [
  ['title' => __('Users'), 'url' => route('app-user-list')],
  ['title' => __('Edit Contractor User')],
]])

<div id="contractor-user-form-error" class="alert alert-danger d-none"></div>

@include('tenant.users._contractor-user-form', ['mode' => 'edit'])

@endsection
