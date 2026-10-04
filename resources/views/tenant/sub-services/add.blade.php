@extends('layouts/layoutMaster')

@section('title', __('Create New Sub-service'))

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
@include('tenant.sub-services._translations')
@vite('resources/assets/js/app-sub-service-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Sub-service'), 'breadcrumbs' => [
  ['title' => __('Sub Services'), 'url' => route('app-sub-service-list')],
  ['title' => __('Create New')],
]])

<div id="sub-service-form-error" class="alert alert-danger d-none"></div>

@include('tenant.sub-services._form', ['mode' => 'create'])

@endsection
