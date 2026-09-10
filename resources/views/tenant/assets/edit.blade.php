@extends('layouts/layoutMaster')

@section('title', __('Edit Asset'))

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
  window.assetFormTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    pick_one: @json(__('Select at least one option.')),
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    list_url: @json(route('app-asset-list')),
    view_url_base: @json(url('/app/asset/view'))
  };
  window.assetEditId = {{ $assetId }};
</script>
@vite('resources/assets/js/app-asset-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Edit Asset Details'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Edit Asset')],
]])

<div id="asset-form-error" class="alert alert-danger d-none"></div>

@include('tenant.assets._form', ['mode' => 'edit'])

@endsection
