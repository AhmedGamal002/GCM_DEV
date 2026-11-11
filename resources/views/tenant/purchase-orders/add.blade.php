@extends('layouts/layoutMaster')

@section('title', __('Create New Purchase Order'))

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
@include('tenant.purchase-orders._form-translations')
@vite('resources/assets/js/app-po-add.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Create New Purchase Order'), 'breadcrumbs' => [
  ['title' => __('Purchase Orders'), 'url' => route('app-po-list')],
  ['title' => __('Create New Purchase Order')],
]])

<div id="po-form-error" class="alert alert-danger d-none"></div>

@include('tenant.purchase-orders._form', ['mode' => 'create'])

@endsection
