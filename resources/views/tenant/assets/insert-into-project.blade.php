@extends('layouts/layoutMaster')

@section('title', __('Insert Asset into Project'))

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/select2/select2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/select2/select2.js'
])
@endsection

@section('page-script')
<script>
  window.assetInsertTranslations = {
    generic_error: @json(__('Something went wrong. Please try again.')),
    required: @json(__('This field is required.')),
    select_company: @json(__('Select company')),
    select_project: @json(__('Select project')),
    select_asset: @json(__('Select asset')),
    no_projects: @json(__('This company has no active projects.')),
    no_assets: @json(__('There are no available assets of this type.')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    project_view_url_base: @json(url('/app/project/view')),
    list_url: @json(route('app-asset-list'))
  };
</script>
@vite('resources/assets/js/app-asset-insert-into-project.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['pageTitle' => __('Insert Asset into Project'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Insert Asset into Project')],
]])

<div id="insert-form-error" class="alert alert-danger d-none"></div>

<form id="insertAssetForm" novalidate>
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      <p class="text-muted mb-6">{{ __('Leaving an asset at a project takes it out of the available assets, so the asset balances match what is really on site.') }}</p>

      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="company_id">{{ __('Client company') }}</label>
          <select id="company_id" class="select2 form-select" data-placeholder="{{ __('Select company') }}" required>
            <option value="">{{ __('Select company') }}</option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="project_id">{{ __('Project') }}</label>
          <select id="project_id" class="select2 form-select" data-placeholder="{{ __('Select project') }}" required disabled>
            <option value="">{{ __('Select project') }}</option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Asset type') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="asset_type" id="at-container" value="container" checked>
            <label class="form-check-label" for="at-container">{{ __('Container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="asset_type" id="at-tank" value="tank">
            <label class="form-check-label" for="at-tank">{{ __('Tank') }}</label>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="asset_id">{{ __('Asset') }}</label>
          <select id="asset_id" class="select2 form-select" data-placeholder="{{ __('Select asset') }}" required>
            <option value="">{{ __('Select asset') }}</option>
          </select>
          <div class="form-text" id="asset-hint"></div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-asset-list') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
  </div>
</form>

@endsection
