@extends('layouts/layoutMaster')

@section('title', __('Asset capacity categories'))

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
  window.assetCategoryListTranslations = {
    all_types: @json(__('All types')),
    container: @json(__('Containers')),
    tank: @json(__('Tanks')),
    both: @json(__('All')),
    edit: @json(__('Edit')),
    actions: @json(__('Actions')),
    add_category: @json(__('Add category')),
    search: @json(__('Search')),
    none_found: @json(__('No categories found.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    export: @json(__('Export')),
    info: @json(__('Showing _START_ to _END_ of _TOTAL_ entries')),
    info_empty: @json(__('Showing 0 to 0 of 0 entries')),
    created: @json(__('Category created successfully.')),
    add_category_url: @json(route('app-asset-category-add')),
    edit_url_base: @json(url('/app/asset-category/edit'))
  };
</script>
@vite(['resources/assets/js/datatables-server-side.js', 'resources/assets/js/app-asset-category-list.js'])
@endsection

@section('content')

@include('_partials.breadcrumb', ['breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Asset capacity categories')],
]])

<div id="asset-category-list-status" class="alert alert-success d-none"></div>

<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('Filters') }}</h5>
    <div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
      <div class="col-md-4 category_type"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-asset-categories table">
      <thead class="border-top">
        <tr>
          <th></th>
          <th>{{ __('Capacity name') }}</th>
          <th>{{ __('Applies to') }}</th>
          <th>{{ __('Capacity (CBM)') }}</th>
          <th>{{ __('Capacity (TON)') }}</th>
          <th>{{ __('Assets') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@endsection
