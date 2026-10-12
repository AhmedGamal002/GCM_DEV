@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="vehicle-category-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="vehicleCategoryForm" class="{{ $isCreate ? '' : 'd-none' }}" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name_en">{{ __('Name (English)') }}</label>
          <input type="text" id="name_en" name="name_en" class="form-control" maxlength="255" required dir="ltr" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="name_ar">{{ __('Name (Arabic)') }}</label>
          <input type="text" id="name_ar" name="name_ar" class="form-control" maxlength="255" required dir="rtl" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Client request: the values most vehicles of this category share. The create-vehicle form
       pre-fills them as soon as the category is picked; each vehicle can still change them. --}}
  <div class="card mb-6">
    <h5 class="card-header">{{ __('Default embedded container') }}</h5>
    <div class="card-body">
      <p class="text-muted mb-6">{{ __('Pre-filled when a vehicle of this category is created. They can still be changed for each vehicle.') }}</p>
      <div class="row g-6">
        <div class="col-12">
          <label class="form-label d-block">{{ __('Does the vehicle have an embedded container?') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="default_has_embedded_container" id="dhec-no" value="0" checked>
            <label class="form-check-label" for="dhec-no">{{ __('No embedded container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="default_has_embedded_container" id="dhec-yes" value="1">
            <label class="form-check-label" for="dhec-yes">{{ __('Has an embedded container') }}</label>
          </div>
        </div>

        <div class="col-md-6 d-none" id="default-embedded-type-wrapper">
          <label class="form-label d-block">{{ __('Embedded container type') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="default_embedded_container_type" id="dect-container" value="container" required>
            <label class="form-check-label" for="dect-container">{{ __('Container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="default_embedded_container_type" id="dect-tank" value="tank" required>
            <label class="form-check-label" for="dect-tank">{{ __('Tank') }}</label>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6 d-none" id="default-embedded-capacity-wrapper">
          <label class="form-label" for="default_embedded_asset_capacity_category_id">{{ __('Capacity') }}</label>
          <select id="default_embedded_asset_capacity_category_id" name="default_embedded_asset_capacity_category_id" class="form-select select2">
            <option value="">{{ __('Select...') }}</option>
          </select>
          <div class="form-text" id="default-embedded-capacity-hint">{{ __('Choose the container type first.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-vehicle-category-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
