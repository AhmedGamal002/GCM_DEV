@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="project-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="projectForm" class="{{ $isCreate ? '' : 'd-none' }}" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      @unless($isCreate)
        <p class="mb-4"><span class="h6">{{ __('ID') }}:</span> <span id="project-code" dir="ltr"></span></p>
      @endunless

      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Project name') }}</label>
          <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="company_id">{{ __('Client company') }}</label>
          @if($isCreate)
            <select id="company_id" name="company_id" class="select2 form-select" data-placeholder="{{ __('Select company') }}" required>
              <option value="">{{ __('Select company') }}</option>
            </select>
          @else
            <input type="text" id="company_name" class="form-control" disabled />
            <div class="form-text">{{ __('The project ID is built from the company, so the company cannot be changed after creation.') }}</div>
          @endif
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="operational_region">{{ __('Operational region') }}</label>
          <input type="text" id="operational_region" name="operational_region" class="form-control" maxlength="255" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="phone">{{ __('Phone') }}</label>
          <input type="tel" id="phone" name="phone" class="form-control" maxlength="50" dir="ltr" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="email">{{ __('Email') }}</label>
          <input type="email" id="email" name="email" class="form-control" maxlength="255" dir="ltr" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="address">{{ __('Address') }}</label>
          <input type="text" id="address" name="address" class="form-control" maxlength="500" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="location_url">{{ __('Location (map link)') }}</label>
          <input type="url" id="location_url" name="location_url" class="form-control" maxlength="2048" dir="ltr" placeholder="https://maps.google.com/..." />
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
    <a href="{{ route('app-project-list') }}" id="project-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
