@extends('layouts/layoutMaster')

@section('title', __('Asset Details'))

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('page-script')
<script>
  window.assetViewTranslations = {
    active: @json(__('Active')),
    in_project: @json(__('In a project')),
    no_project: @json(__('This asset is not in any project.')),
    project_view_url_base: @json(url('/app/project/view')),
    on_maintenance: @json(__('On Maintenance')),
    deactivated: @json(__('Deactivated')),
    container: @json(__('Container')),
    tank: @json(__('Tank')),
    gcm: @json(__('GCM')),
    status_updated: @json(__('Asset status updated successfully.')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    edit_url_base: @json(url('/app/asset/edit'))
  };
  window.assetViewId = {{ $assetId }};
</script>
@vite('resources/assets/js/app-asset-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'av-updated-by', 'pageTitle' => __('Asset Details'), 'breadcrumbs' => [
  ['title' => __('Assets'), 'url' => route('app-asset-list')],
  ['title' => __('Asset Details')],
]])

<div id="asset-view-status" class="alert alert-success d-none"></div>
<div id="asset-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="asset-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="asset-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-box ti-36px" id="av-type-icon"></i></span>
          </div>
          <h5 class="mb-1" id="av-name"></h5>
          <span class="badge" id="av-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Type') }}:</span> <span id="av-type"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Asset capacity') }}:</span> <span id="av-capacity"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Affiliation') }}:</span> <span id="av-affiliation"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Entity') }}:</span> <span id="av-entity"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Purchase date') }}:</span> <span id="av-purchase-date"></span></li>
        </ul>
        <div id="av-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="av-additional"></div>
        </div>

        <div class="d-flex justify-content-center">
          <a href="#" id="av-edit-link" class="btn btn-primary me-4">{{ __('Edit') }}</a>
          <a href="{{ route('app-asset-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card mb-6 d-none" id="asset-compat-card">
      <h5 class="card-header">{{ __('Compatible vehicle categories') }}</h5>
      <div class="card-body">
        <div id="av-compat" class="d-flex flex-wrap gap-2"></div>
      </div>
    </div>

    <div class="card mb-6">
      <h5 class="card-header">{{ __('Current project') }}</h5>
      <div class="card-body">
        <p class="mb-0" id="av-project">—</p>
      </div>
    </div>

    <div class="card d-none" id="asset-status-card">
      <h5 class="card-header">{{ __('Asset Status') }}</h5>
      <div class="card-body">
        <div class="alert alert-warning">
          <h5 class="alert-heading mb-1">{{ __('Change this asset\'s operational status?') }}</h5>
          <p class="mb-0">{{ __('An asset on maintenance or deactivated cannot be selected for a trip.') }}</p>
        </div>
        <form id="assetStatusForm">
          <div class="mb-6">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-active" value="active">
              <label class="form-check-label" for="ns-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-maintenance" value="on_maintenance">
              <label class="form-check-label" for="ns-maintenance">{{ __('On Maintenance') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="new_status" id="ns-deactivated" value="deactivated">
              <label class="form-check-label" for="ns-deactivated">{{ __('Deactivated') }}</label>
            </div>
          </div>
          <div class="form-check my-8">
            <input class="form-check-input" type="checkbox" id="as-confirm" />
            <label class="form-check-label" for="as-confirm">{{ __('I confirm this status change') }}</label>
          </div>
          <button type="submit" class="btn btn-danger" id="as-submit" disabled>{{ __('Update Status') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
