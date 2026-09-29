@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="asset-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="assetForm" class="{{ $isCreate ? '' : 'd-none' }}" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      @unless($isCreate)
        <div class="alert alert-info" role="alert">
          {{ __('Only the asset name can be edited. Capacity, type, affiliation and compatible vehicle categories are locked after creation.') }}
        </div>
      @endunless

      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Asset Name') }}</label>
          <input type="text" id="name" name="name" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
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

        <div class="col-md-6">
          <label class="form-label" for="asset_capacity_category_id">{{ __('Asset capacity') }}</label>
          <select id="asset_capacity_category_id" name="asset_capacity_category_id" class="form-select select2" required>
            <option value="">{{ __('Select...') }}</option>
          </select>
          <div class="form-text">{{ __('Only capacities matching the selected type are shown.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Compatible vehicle categories') }}</label>
          <div id="compatible-vehicle-categories" class="row row-cols-1 row-cols-sm-2 g-2"></div>
          <div class="text-danger small mt-1 d-none" data-feedback data-feedback-for="compatible_vehicle_category_ids"></div>
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

        <div class="col-md-6">
          <label class="form-label" for="purchase_date">{{ __('Purchase date') }}</label>
          <input type="date" id="purchase_date" name="purchase_date" class="form-control" />
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
    <a href="{{ route('app-asset-list') }}" id="asset-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
