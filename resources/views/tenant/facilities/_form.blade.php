@php($isCreate = ($mode ?? 'create') === 'create')

<div class="card mb-6">

  @unless($isCreate)
    <div class="card-body text-center py-6" id="facility-form-loading">
      <div class="spinner-border" role="status"></div>
    </div>
  @endunless

  <form class="card-body {{ $isCreate ? '' : 'd-none' }}" id="facilityForm" novalidate>

    @unless($isCreate)
      <div class="alert alert-info" role="alert">
        {{ __('The prefix can\'t be changed after creation. The environmental service and recycling efficiency can be changed only while no sub-service or trip uses this facility.') }}
      </div>
      <div class="alert alert-warning d-none" role="alert" id="facility-locked-alert">
        {{ __('This facility is in use, so its environmental service and recycling efficiency are locked.') }}
      </div>
    @endunless

    <h6>1. {{ __('Basic Data') }}</h6>
    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="name">{{ __('Facility name') }}</label>
        <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="prefix">{{ __('Prefix name') }}</label>
        <input type="text" id="prefix" name="prefix" class="form-control text-uppercase" maxlength="3" dir="ltr" autocomplete="off" required />
        <div class="form-text">{{ __('3 unique English letters that identify the facility, used with numbers to build a unique reference.') }}</div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="logo">{{ __('Logo') }}</label>
        <input type="file" id="logo" name="logo" class="form-control" accept="image/*" />
        <div class="form-text d-none" id="logo_current"></div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label d-block">{{ __('Environmental service') }}</label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="environmental_service" id="es-disposal" value="disposal" required>
          <label class="form-check-label" for="es-disposal">{{ __('Safe disposal') }}</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="environmental_service" id="es-sewage" value="sewage_treatment">
          <label class="form-check-label" for="es-sewage">{{ __('Sewage treatment') }}</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="environmental_service" id="es-recycle" value="recycle">
          <label class="form-check-label" for="es-recycle">{{ __('Recycling') }}</label>
        </div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6 d-none" id="recycling-efficiency-group">
        <label class="form-label" for="recycling_efficiency">{{ __('Recycling efficiency (%)') }}</label>
        <input type="number" id="recycling_efficiency" name="recycling_efficiency" class="form-control" min="0" max="100" step="0.01" dir="ltr" />
        <div class="form-text">{{ __('Of every trip to this facility, this share is counted as recycled and the rest as landfilled in the diversion report.') }}</div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      @if($isCreate)
        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Operational status') }}</label>
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

      <div class="col-md-6">
        <label class="form-label" for="address">{{ __('Address') }}</label>
        <input type="text" id="address" name="address" class="form-control" maxlength="255" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="location_url">{{ __('Location (map link)') }}</label>
        <input type="url" id="location_url" name="location_url" class="form-control" maxlength="2048" dir="ltr" placeholder="https://maps.google.com/..." />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>

    <hr class="my-6 mx-n4" />
    <h6>2. {{ __('Contract') }}</h6>
    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="contract_number">{{ __('Contract No.') }}</label>
        <input type="text" id="contract_number" name="contract_number" class="form-control" maxlength="100" dir="ltr" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="contract_attachment">{{ __('Contract attachment') }}</label>
        <input type="file" id="contract_attachment" name="contract_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
        <div class="form-text d-none" id="contract_attachment_current"></div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="contract_start">{{ __('Start date') }}</label>
        <input type="date" id="contract_start" name="contract_start" class="form-control" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="contract_end">{{ __('End date') }}</label>
        <input type="date" id="contract_end" name="contract_end" class="form-control" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>

    <hr class="my-6 mx-n4" />
    <h6>3. {{ __('Additional Data') }}</h6>
    <div class="row g-6">
      <div class="col-12">
        <div class="comment-editor border" id="additional-data-editor"></div>
      </div>
    </div>

    <div class="pt-6">
      <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
      <a href="{{ route('app-facility-list') }}" id="facility-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
  </form>
</div>
