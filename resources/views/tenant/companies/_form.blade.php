@php($isCreate = ($mode ?? 'create') === 'create')

<div class="card mb-6">

  @unless($isCreate)
    <div class="card-body text-center py-6" id="company-form-loading">
      <div class="spinner-border" role="status"></div>
    </div>
  @endunless

  <form class="card-body {{ $isCreate ? '' : 'd-none' }}" id="companyForm" enctype="multipart/form-data" novalidate>

    @unless($isCreate)
      <p class="mb-1"><span class="h6">{{ __('ID') }}:</span> <span id="company-code" dir="ltr"></span></p>

    @endunless

    <h6>1. {{ __('Basic Data') }}</h6>
    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="name">{{ __('Company name') }}</label>
        <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="prefix">{{ __('Short name') }}</label>
        <input type="text" id="prefix" name="prefix" class="form-control text-uppercase" maxlength="3" dir="ltr" required {{ $isCreate ? '' : 'disabled' }} />
        <div class="form-text">{{ __('3 unique English letters that identify the company. The company ID and its future project, contract and trip numbers are built from them, so they cannot be changed after creation.') }}</div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="business_sector">{{ __('Business sector') }}</label>
        <input type="text" id="business_sector" name="business_sector" class="form-control" maxlength="255" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="logo">{{ __('Logo') }}</label>
        <input type="file" id="logo" name="logo" class="form-control" accept="image/*" />
        <div class="form-text">{{ __('Allowed JPG, PNG. Max size of 2MB.') }}</div>
        <div class="small mt-1 d-none" id="current-logo"></div>
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

    <hr class="my-6 mx-n4" />
    <h6>2. {{ __('Contract') }}</h6>
    <div class="row g-6">
      <div class="col-md-4">
        <label class="form-label" for="contract_number">{{ __('Contract No.') }}</label>
        <input type="text" id="contract_number" name="contract_number" class="form-control" inputmode="numeric" maxlength="50" dir="ltr" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="contract_start_date">{{ __('Start date') }}</label>
        <input type="date" id="contract_start_date" name="contract_start_date" class="form-control" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="contract_end_date">{{ __('End date') }}</label>
        <input type="date" id="contract_end_date" name="contract_end_date" class="form-control" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="contract_attachment">{{ __('Contract copy') }}</label>
        <input type="file" id="contract_attachment" name="contract_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
        <div class="form-text">{{ __('PDF, JPG or PNG — max 4MB.') }}</div>
        <div class="small mt-1 d-none" id="current-contract"></div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>

    <hr class="my-6 mx-n4" />
    <h6>3. {{ __('Commercial & tax registration') }}</h6>
    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="cr_number">{{ __('Commercial registration No.') }}</label>
        <input type="text" id="cr_number" name="cr_number" class="form-control" inputmode="numeric" maxlength="50" dir="ltr" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="cr_attachment">{{ __('Commercial registration copy') }}</label>
        <input type="file" id="cr_attachment" name="cr_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
        <div class="small mt-1 d-none" id="current-cr"></div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="tax_number">{{ __('Tax registration No.') }}</label>
        <input type="text" id="tax_number" name="tax_number" class="form-control" inputmode="numeric" maxlength="50" dir="ltr" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="tax_attachment">{{ __('Tax registration copy') }}</label>
        <input type="file" id="tax_attachment" name="tax_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
        <div class="small mt-1 d-none" id="current-tax"></div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>

    <hr class="my-6 mx-n4" />
    <h6>4. {{ __('Additional Data') }}</h6>
    <div class="row g-6">
      <div class="col-12">
        <div class="comment-editor border" id="additional-data-editor"></div>
      </div>
    </div>

    <div class="pt-6">
      <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
      <a href="{{ route('app-company-list') }}" id="company-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
    </div>
  </form>
</div>
