@php($isCreate = ($mode ?? 'create') === 'create')

<div class="card mb-6">

  @unless($isCreate)
    <div class="card-body text-center py-6" id="vehicle-category-form-loading">
      <div class="spinner-border" role="status"></div>
    </div>
  @endunless

  <form class="card-body {{ $isCreate ? '' : 'd-none' }}" id="vehicleCategoryForm" novalidate>

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

    <div class="pt-6">
      <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
      <a href="{{ route('app-vehicle-category-list') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
  </form>
</div>
