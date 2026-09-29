@extends('layouts/layoutMaster')

@section('title', __('Edit Facility Details'))

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
    cancel: @json(__('Cancel')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    current_file: @json(__('Current file')),
    replace_hint: @json(__('Choose a new file to replace it.')),
    list_url: @json(route('app-facility-list')),
    view_url_base: @json(url('/app/facility/view')),
    contract_url_base: @json(url('/api/v1/facilities'))
  };
  window.facilityEditId = {{ $facilityId }};
</script>
@vite('resources/assets/js/app-facility-edit.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'facility-last-updated', 'pageTitle' => __('Edit Facility Details'), 'breadcrumbs' => [
  ['title' => __('Facilities'), 'url' => route('app-facility-list')],
  ['title' => __('Edit Facility')],
]])

<div id="facility-form-error" class="alert alert-danger d-none"></div>

@include('tenant.facilities._form', ['mode' => 'edit'])

@endsection
