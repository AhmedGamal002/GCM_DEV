@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="main-service-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>

  {{-- FRD §1.9.1: nothing is editable once a PO includes the service. --}}
  <div class="alert alert-warning d-none" id="main-service-locked">
    {{ __('This main service is included in a purchase order, so it can\'t be edited or deactivated.') }}
  </div>
@endunless

<form id="mainServiceForm" class="{{ $isCreate ? '' : 'd-none' }}" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Name') }}</label>
          <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Type of Service') }}</label>
          @foreach (['non_hazardous' => __('Non-Hazardous waste'), 'hazardous' => __('Hazardous waste'), 'supplying' => __('Supplying')] as $value => $label)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="service_type" id="st-{{ $value }}" value="{{ $value }}" @disabled(! $isCreate)>
              <label class="form-check-label" for="st-{{ $value }}">{{ $label }}</label>
            </div>
          @endforeach
          @unless($isCreate)
            <div class="form-text">{{ __('The type of service can\'t be changed after creation — the sub-services under it are built on it.') }}</div>
          @endunless
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        @if($isCreate)
          <div class="col-md-6">
            <label class="form-label d-block">{{ __('Service Status') }}</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-active" value="active" checked>
              <label class="form-check-label" for="os-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-deactivated" value="deactivated">
              <label class="form-check-label" for="os-deactivated">{{ __('Deactivated') }}</label>
            </div>
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Supported Vehicle Categories') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12" data-error-for="vehicle_category_ids">
          <div class="row g-3" id="vehicle-categories-box">
            <div class="col-12 text-muted small">{{ __('Loading...') }}</div>
          </div>
          <div class="text-danger small mt-2 d-none" data-feedback></div>
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
    <a href="{{ route('app-main-service-list') }}" id="main-service-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
