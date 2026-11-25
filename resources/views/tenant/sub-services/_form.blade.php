@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="sub-service-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>

  {{-- FRD §1.9.2: nothing is editable while tied to an ongoing trip / its main service is in a PO. --}}
  <div class="alert alert-warning d-none" id="sub-service-locked">
    {{ __('This sub-service is used in a trip or its main service is in a purchase order, so it can\'t be edited or deactivated.') }}
  </div>
@endunless

<form id="subServiceForm" class="{{ $isCreate ? '' : 'd-none' }}" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      @unless($isCreate)
        <div class="alert alert-info" role="alert">
          {{ __('Only the name, the skip color code and the additional data can be edited. The main service, type, capacities, facilities and the recycle receipt setting are locked after creation.') }}
        </div>
      @endunless

      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Service name') }}</label>
          <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="main_service_id">{{ __('Main service') }}</label>
          @if($isCreate)
            <select id="main_service_id" name="main_service_id" class="select2 form-select" data-placeholder="{{ __('Select main service') }}" required>
              <option value="">{{ __('Select main service') }}</option>
            </select>
          @else
            <input type="text" id="main_service_name" class="form-control" disabled />
            <div class="form-text">{{ __('The main service can\'t be changed after creation.') }}</div>
          @endif
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Supported Vehicle Categories') }}</label>
          <div id="supported-categories" class="d-flex flex-wrap gap-1">
            <small class="text-muted">{{ __('Shown automatically from the main service.') }}</small>
          </div>
        </div>

        @if($isCreate)
          <div class="col-md-6">
            <label class="form-label d-block">{{ __('Service Status') }}</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-active" value="active" checked required>
              <label class="form-check-label" for="os-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-deactivated" value="deactivated" required>
              <label class="form-check-label" for="os-deactivated">{{ __('Deactivated') }}</label>
            </div>
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Compatible Assets Type & Capacity') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label d-block">{{ __('Type') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="asset_type" id="at-container" value="container" required>
            <label class="form-check-label" for="at-container">{{ __('Container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="asset_type" id="at-tank" value="tank" required>
            <label class="form-check-label" for="at-tank">{{ __('Tank') }}</label>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-12" data-error-for="capacity_ids">
          <label class="form-label d-block">{{ __('Compatible capacities') }}</label>
          <div class="row g-3" id="capacities-box" data-required>
            <div class="col-12 text-muted small">{{ __('Choose the asset type first.') }}</div>
          </div>
          <div class="text-danger small mt-2 d-none" data-feedback></div>
        </div>

        {{-- FRD: vehicles with a built-in container are shown as information only. --}}
        <div class="col-12 d-none" id="embedded-info">
          <div class="alert alert-secondary mb-0">
            <h6 class="alert-heading mb-2">{{ __('Embedded containers in the supported vehicles (information only)') }}</h6>
            <ul class="mb-0 ps-4" id="embedded-list"></ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Waste disposal & recycle facilities') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12" data-error-for="facility_ids">
          <p class="small text-muted mb-3 d-none" id="facilities-optional-note">{{ __('Optional — the main service is a supplying service.') }}</p>
          <div class="row g-3" id="facilities-box">
            <div class="col-12 text-muted small">{{ __('Loading...') }}</div>
          </div>
          <div class="text-danger small mt-2 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Service Settings') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="requires_recycle_receipt" name="requires_recycle_receipt">
            <label class="form-check-label" for="requires_recycle_receipt">{{ __('Requires Recycle Receipt') }}</label>
          </div>
          <div class="form-text">{{ __('When on, a recycle receipt must be uploaded for every trip of this service.') }}</div>
        </div>

        <div class="col-12" data-error-for="skip_color_code">
          <label class="form-label d-block">{{ __('Skip Color Code') }} <small class="text-muted">({{ __('Optional') }})</small></label>
          <div class="row g-2">
            <div class="col-md-4 col-sm-6">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="skip_color_code" id="scc-none" value="" checked>
                <label class="form-check-label" for="scc-none">{{ __('None') }}</label>
              </div>
            </div>
            @foreach (\App\Models\SubService::SKIP_COLORS as $slug => [$label, $hex])
              <div class="col-md-4 col-sm-6">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="skip_color_code" id="scc-{{ $slug }}" value="{{ $slug }}">
                  <label class="form-check-label d-flex align-items-center" for="scc-{{ $slug }}">
                    <span class="d-inline-block rounded-circle border me-2" style="width:14px;height:14px;background:{{ $hex ?? 'transparent' }}{{ $hex ? '' : ';border-style:dashed !important' }}"></span>
                    {{ __($label) }}
                  </label>
                </div>
              </div>
            @endforeach
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Additional Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <div class="comment-editor border" id="additional-data-editor"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-sub-service-list') }}" id="sub-service-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
