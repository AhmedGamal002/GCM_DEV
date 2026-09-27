@php($isCreate = ($mode ?? 'create') === 'create')

<div class="card mb-6">

  @unless($isCreate)
    <div class="card-body text-center py-6" id="asset-category-form-loading">
      <div class="spinner-border" role="status"></div>
    </div>
  @endunless

  <form class="card-body {{ $isCreate ? '' : 'd-none' }}" id="assetCategoryForm" novalidate>

    @unless($isCreate)
      <div class="alert alert-info" role="alert">
        {{ __('Only the name can be edited. The capacity and applicability are locked after creation.') }}
        <span class="d-block mt-1 small" id="asset-category-last-updated"></span>
      </div>
    @endunless

    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="name">{{ __('Category name') }}</label>
        <input type="text" id="name" name="name" class="form-control" required />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="applies_to">{{ __('Applies to') }}</label>
        <select id="applies_to" name="applies_to" class="form-select" required>
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

      <div class="col-12">
        <label class="form-label" for="additional_data">{{ __('Additional data') }}</label>
        <textarea id="additional_data" name="additional_data" class="form-control" rows="3"></textarea>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>

    <div class="pt-6">
      <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
      <a href="{{ route('app-asset-category-list') }}" id="asset-category-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
  </form>
</div>
