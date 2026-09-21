@extends('layouts/layoutMaster')

@section('title', __('Vehicle Categories'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
@endsection

@section('page-script')
<script>
  window.vehicleCategoryListTranslations = {
    edit: @json(__('Edit')),
    delete: @json(__('Delete')),
    actions: @json(__('Actions')),
    add_category: @json(__('Add category')),
    search: @json(__('Search')),
    none_found: @json(__('No categories found.')),
    in_use_hint: @json(__('In use — can\'t be deleted')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Category created successfully.')),
    saved: @json(__('Changes saved successfully.')),
    deleted: @json(__('Category deleted.')),
    add_category_url: @json(route('app-vehicle-category-add')),
    edit_url_base: @json(url('/app/vehicle-category/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-vehicle-category-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Vehicle Categories'), 'breadcrumbs' => [
  ['title' => __('Vehicles'), 'url' => route('app-vehicle-list')],
  ['title' => __('Categories')],
]])

<div id="vehicle-category-list-status" class="alert alert-success d-none"></div>
<div id="vehicle-category-list-error" class="alert alert-danger d-none"></div>

<div class="card">
  <div class="card-datatable table-responsive">
    <table class="datatables-vehicle-categories table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Name (English)') }}</th>
          <th>{{ __('Name (Arabic)') }}</th>
          <th>{{ __('Vehicles') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Delete category') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cancel') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">{{ __('Are you sure you want to delete this category?') }} <strong id="delete-category-name"></strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-danger" id="delete-category-confirm">{{ __('Delete') }}</button>
      </div>
    </div>
  </div>
</div>

@endsection
