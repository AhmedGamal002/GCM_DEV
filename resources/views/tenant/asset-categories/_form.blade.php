@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="asset-category-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form class="{{ $isCreate ? '' : 'd-none' }}" id="assetCategoryForm" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      @unless($isCreate)
        <div class="alert alert-info" role="alert">
          {{ __('Only the name and additional data can be edited. The capacity and applicability are locked after creation.') }}
        </div>
      @endunless

      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Capacity name') }}</label>
          <input type="text" id="name" name="name" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="applies_to">{{ __('Applies to') }}</label>
          <select id="applies_to" name="applies_to" class="form-select select2" required>
            <option value="container">{{ __('Containers') }}</option>
            <option value="tank">{{ __('Tanks') }}</option>
            <option value="both">{{ __('All') }}</option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="capacity_cbm">{{ __('Capacity (CBM)') }}</label>
          <input type="number" step="0.01" min="0" id="capacity_cbm" name="capacity_cbm" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="capacity_ton">{{ __('Capacity (TON)') }}</label>
          <input type="number" step="0.01" min="0" id="capacity_ton" name="capacity_ton" class="form-control" required />
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
    <a href="{{ route('app-asset-category-list') }}" id="asset-category-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
