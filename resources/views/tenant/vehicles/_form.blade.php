@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="vehicle-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="vehicleForm" class="{{ $isCreate ? '' : 'd-none' }}" enctype="multipart/form-data" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-3">
          <label class="form-label" for="plate_letters">{{ __('Plate letters (English)') }}</label>
          <input type="text" id="plate_letters" name="plate_letters" class="form-control text-uppercase" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="plate_numbers">{{ __('Plate numbers') }}</label>
          <input type="text" inputmode="numeric" data-numeric id="plate_numbers" name="plate_numbers" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="vehicle_category_id">{{ __('Vehicle category') }}</label>
          <select id="vehicle_category_id" name="vehicle_category_id" class="form-select select2" required>
            <option value="">{{ __('Select...') }}</option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-12">
          <label class="form-label d-block">{{ __('Does the vehicle have an embedded container?') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="has_embedded_container" id="hec-no" value="0" checked>
            <label class="form-check-label" for="hec-no">{{ __('No embedded container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="has_embedded_container" id="hec-yes" value="1">
            <label class="form-check-label" for="hec-yes">{{ __('Has an embedded container') }}</label>
          </div>
        </div>

        <div class="col-md-6 d-none" id="embedded-type-wrapper">
          <label class="form-label d-block">{{ __('Embedded container type') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="embedded_container_type" id="ect-container" value="container" required>
            <label class="form-check-label" for="ect-container">{{ __('Container') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="embedded_container_type" id="ect-tank" value="tank" required>
            <label class="form-check-label" for="ect-tank">{{ __('Tank') }}</label>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6 d-none" id="embedded-capacity-wrapper">
          <label class="form-label" for="embedded_asset_capacity_category_id">{{ __('Capacity') }}</label>
          <select id="embedded_asset_capacity_category_id" name="embedded_asset_capacity_category_id" class="form-select select2" required>
            <option value="">{{ __('Select...') }}</option>
          </select>
          <div class="form-text" id="embedded-capacity-hint">{{ __('Choose the vehicle category and container type first.') }}</div>
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
              <input class="form-check-input" type="radio" name="operational_status" id="os-maintenance" value="on_maintenance">
              <label class="form-check-label" for="os-maintenance">{{ __('On Maintenance') }}</label>
            </div>
            {{-- FRD V01.14: deactivation is (system_admin / data_entry) now, same as create/edit — was system_admin-only under V01.09. --}}
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-deactivated" value="deactivated">
              <label class="form-check-label" for="os-deactivated">{{ __('Deactivated') }}</label>
            </div>
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
        @endif

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Affiliation') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="affiliation" id="aff-gcm" value="gcm" checked>
            <label class="form-check-label" for="aff-gcm">{{ __('Belongs to GCM') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="affiliation" id="aff-contractor" value="contractor" disabled>
            <label class="form-check-label text-muted" for="aff-contractor">{{ __('Belongs to a Contractor') }} — {{ __('coming soon') }}</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Vehicle Photos') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="photo_front">{{ __('Front view') }}</label>
          <input type="file" id="photo_front" name="photo_front" class="form-control" accept="image/*" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
          <div class="form-text d-none" id="photo_front_current"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="photo_back">{{ __('Back view') }}</label>
          <input type="file" id="photo_back" name="photo_back" class="form-control" accept="image/*" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
          <div class="form-text d-none" id="photo_back_current"></div>
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

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Documents') }}</h5>
    <div class="card-body">
      @php($docTypes = [
        'registration_card' => __('Registration card'),
        'fitness_document' => __('Fitness document'),
        'inspection_certificate' => __('Inspection certificate'),
        'insurance' => __('Vehicle insurance'),
      ])
      @foreach($docTypes as $type => $label)
        <div class="card border shadow-none mb-4" data-doc-type="{{ $type }}">
          <div class="card-body">
            <h6 class="mb-4">{{ $label }}</h6>
            <div class="row g-4">
              <div class="col-md-4">
                <label class="form-label" for="doc-{{ $type }}-number">{{ __('Document number') }}</label>
                <input type="text" inputmode="numeric" data-numeric id="doc-{{ $type }}-number" name="documents[{{ $type }}][number]" class="form-control" required />
                <div class="text-danger small mt-1 d-none" data-feedback></div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="doc-{{ $type }}-valid">{{ __('Valid to') }}</label>
                <input type="date" id="doc-{{ $type }}-valid" name="documents[{{ $type }}][valid_to]" class="form-control" required />
                <div class="text-danger small mt-1 d-none" data-feedback></div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="doc-{{ $type }}-file">{{ __('Attachment') }}</label>
                <input type="file" id="doc-{{ $type }}-file" name="documents[{{ $type }}][attachment]" class="form-control" />
                <div class="text-danger small mt-1 d-none" data-feedback></div>
                <div class="form-text d-none" data-doc-current="{{ $type }}"></div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ __('Truck entry permits') }}</h5>
      <button type="button" class="btn btn-sm btn-label-primary" id="add-entry-permit">
        <i class="ti ti-plus me-1"></i>{{ __('Add permit') }}
      </button>
    </div>
    <div class="card-body">
      <div id="entry-permits-container"></div>
    </div>
  </div>
  <template id="entry-permit-template">
    <div class="card border shadow-none mb-4 entry-permit-row">
      <div class="card-body">
        <input type="hidden" data-field="id" />
        <div class="row g-4">
          <div class="col-md-3">
            <label class="form-label">{{ __('Area name') }}</label>
            <input type="text" data-field="area_name" class="form-control" required />
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Permit number') }}</label>
            <input type="text" inputmode="numeric" data-numeric data-field="permit_number" class="form-control" required />
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Valid to') }}</label>
            <input type="date" data-field="valid_to" class="form-control" required />
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Attachment') }}</label>
            <input type="file" data-field="attachment" class="form-control" />
            <div class="text-danger small mt-1 d-none" data-feedback></div>
            <div class="form-text d-none" data-permit-current></div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-text-danger mt-3 remove-entry-permit">
          <i class="ti ti-trash me-1"></i>{{ __('Remove') }}
        </button>
      </div>
    </div>
  </template>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-vehicle-list') }}" id="vehicle-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
