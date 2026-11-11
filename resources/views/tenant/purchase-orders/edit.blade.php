@extends('layouts/layoutMaster')

@section('title', __('Edit Purchase Order'))

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
@vite('resources/assets/js/app-po-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'po-last-updated', 'pageTitle' => __('Edit Purchase Order Details'), 'breadcrumbs' => [
  ['title' => __('Purchase Orders'), 'url' => route('app-po-list')],
  ['title' => __('Edit Purchase Order')],
]])

<div id="po-form-error" class="alert alert-danger d-none"></div>
<div id="po-status-ok" class="alert alert-success d-none"></div>

@include('tenant.purchase-orders._form', ['mode' => 'edit'])

{{-- FRD V01.14 §1.13: deactivating / re-activating is the System Admin's, with or without trips on the PO. --}}
<div class="card d-none mt-6" id="po-status-card">
  <h5 class="card-header">{{ __('Purchase Order Status') }}</h5>
  <div class="card-body">
    <div class="alert alert-warning">
      <h5 class="alert-heading mb-1">{{ __('Change this purchase order\'s status?') }}</h5>
      <p class="mb-0">{{ __('A deactivated purchase order is kept for its history but is not offered for new trips, and its services no longer block another purchase order.') }}</p>
    </div>
    <form id="poStatusForm">
      <div class="mb-6">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="new_status" id="ns-active" value="active">
          <label class="form-check-label" for="ns-active">{{ __('Active') }}</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="new_status" id="ns-deactivated" value="deactivated">
          <label class="form-check-label" for="ns-deactivated">{{ __('Deactivated') }}</label>
        </div>
      </div>
      <div class="form-check my-8">
        <input class="form-check-input" type="checkbox" id="ps-confirm" />
        <label class="form-check-label" for="ps-confirm">{{ __('I confirm this status change') }}</label>
      </div>
      <button type="submit" class="btn btn-danger" id="ps-submit" disabled>{{ __('Update Status') }}</button>
    </form>
  </div>
</div>

@endsection
