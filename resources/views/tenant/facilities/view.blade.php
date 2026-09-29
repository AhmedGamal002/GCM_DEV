@extends('layouts/layoutMaster')

@section('title', __('Intermediate Facility Details'))

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-user-view.scss'])
@endsection

@section('page-script')
<script>
  window.facilityViewTranslations = {
    active: @json(__('Active')),
    deactivated: @json(__('Deactivated')),
    disposal: @json(__('Safe disposal')),
    sewage_treatment: @json(__('Sewage treatment')),
    recycle: @json(__('Recycling')),
    status_updated: @json(__('Facility status updated successfully.')),
    saved: @json(__('Changes saved successfully.')),
    generic_error: @json(__('Something went wrong. Please try again.')),
    last_updated_by: @json(__('Last updated by :name — :at')),
    edit_url_base: @json(url('/app/facility/edit')),
    contract_url_base: @json(url('/api/v1/facilities')),
    open_map: @json(__('Open on the map')),
    download_contract: @json(__('Download contract')),
    no_sub_services: @json(__('No sub-services are linked to this facility yet.'))
  };
  window.facilityViewId = {{ $facilityId }};
  window.facilityViewCanManage = @json(auth()->user()->can('create', \App\Models\IntermediateFacility::class));
</script>
@vite('resources/assets/js/app-facility-view.js')
@endsection

@section('content')

@include('_partials.breadcrumb', ['updatedId' => 'fv-updated-by', 'pageTitle' => __('Intermediate Facility Details'), 'breadcrumbs' => [
  ['title' => __('Facilities'), 'url' => route('app-facility-list')],
  ['title' => __('Facility Details')],
]])

<div id="facility-view-status" class="alert alert-success d-none"></div>
<div id="facility-view-error" class="alert alert-danger d-none"></div>

<div class="row">
  <div class="col-xl-4 col-lg-5">
    <div class="card mb-6">
      <div class="card-body text-center py-6" id="facility-view-loading">
        <div class="spinner-border" role="status"></div>
      </div>
      <div class="card-body pt-6 d-none" id="facility-view-content">
        <div class="d-flex align-items-center flex-column">
          <div class="avatar avatar-xl mb-4" id="fv-logo-wrapper">
            <img src="" alt="" class="rounded d-none" id="fv-logo" />
            <span class="avatar-initial rounded bg-label-primary" id="fv-logo-fallback"><i class="ti ti-building-factory-2 ti-36px"></i></span>
          </div>
          <h5 class="mb-1" id="fv-name"></h5>
          <span class="badge" id="fv-status"></span>
        </div>

        <h5 class="pb-4 border-bottom mb-4 mt-6">{{ __('Details') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('ID') }}:</span> <span id="fv-code" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Prefix name') }}:</span> <span id="fv-prefix" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Environmental service') }}:</span> <span id="fv-service"></span></li>
          <li class="mb-2 d-none" id="fv-efficiency-row"><span class="h6">{{ __('Recycling efficiency') }}:</span> <span id="fv-efficiency"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Address') }}:</span> <span id="fv-address"></span></li>
          <li class="mb-2 d-none" id="fv-map-row"><span class="h6">{{ __('Location') }}:</span> <a href="#" id="fv-map" target="_blank" rel="noopener"></a></li>
        </ul>

        <h5 class="pb-4 border-bottom mb-4">{{ __('Contract') }}</h5>
        <ul class="list-unstyled mb-6">
          <li class="mb-2"><span class="h6">{{ __('Contract No.') }}:</span> <span id="fv-contract-number" dir="ltr"></span></li>
          <li class="mb-2"><span class="h6">{{ __('Start date') }}:</span> <span id="fv-contract-start"></span></li>
          <li class="mb-2"><span class="h6">{{ __('End date') }}:</span> <span id="fv-contract-end"></span></li>
          <li class="mb-2 d-none" id="fv-contract-file-row"><a href="#" id="fv-contract-file" target="_blank" rel="noopener"><i class="ti ti-paperclip me-1"></i><span></span></a></li>
        </ul>

        <div id="fv-additional-wrapper" class="mb-6 d-none">
          <span class="h6 d-block mb-2">{{ __('Additional Data') }}:</span>
          <div id="fv-additional"></div>
        </div>
        <div class="d-flex justify-content-center">
          <a href="#" id="fv-edit-link" class="btn btn-primary me-4 d-none">{{ __('Edit') }}</a>
          <a href="{{ route('app-facility-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8 col-lg-7">
    <div class="card mb-6">
      <h5 class="card-header">{{ __('Supported sub-services') }}</h5>
      <div class="card-body">
        <div id="fv-sub-services" class="d-flex flex-wrap gap-2"></div>
        <div class="alert alert-info mb-0 d-none" id="fv-sub-services-empty"></div>
        <p class="small text-muted mt-3 mb-0">{{ __('The services this facility accepts are taken from the sub-services that list it.') }}</p>
      </div>
    </div>

    <div class="card d-none" id="facility-status-card">
      <h5 class="card-header">{{ __('Facility Status') }}</h5>
      <div class="card-body">
        <div class="alert alert-warning">
          <h5 class="alert-heading mb-1">{{ __('Change this facility\'s operational status?') }}</h5>
          <p class="mb-0">{{ __('A deactivated facility can\'t be chosen for new sub-services or trips.') }}</p>
        </div>
        <form id="facilityStatusForm">
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
            <input class="form-check-input" type="checkbox" id="fs-confirm" />
            <label class="form-check-label" for="fs-confirm">{{ __('I confirm this status change') }}</label>
          </div>
          <button type="submit" class="btn btn-danger" id="fs-submit" disabled>{{ __('Update Status') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
