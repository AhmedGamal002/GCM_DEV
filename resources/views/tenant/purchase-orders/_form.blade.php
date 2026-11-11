@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="po-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="poForm" class="{{ $isCreate ? '' : 'd-none' }}" enctype="multipart/form-data" novalidate>

  {{-- Decision ب2: once a (non-cancelled) trip is recorded on the PO only its quota can change. --}}
  <div class="alert alert-info d-none" id="po-lock-alert">
    {{ __('A trip is recorded on this purchase order, so only the quota of its services can be edited now. The number, company, project, dates, notes and the services themselves are locked.') }}
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Basic Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="paper_number">{{ __('PO No.') }}</label>
          <div class="input-group" dir="ltr">
            <span class="input-group-text" id="po-prefix">—</span>
            <input type="text" id="paper_number" name="paper_number" class="form-control" inputmode="numeric" maxlength="10" pattern="[0-9]*" required />
          </div>
          <div class="form-text">{{ __('The number printed on the paper contract (digits only, up to 10). The system ID is the client\'s short name followed by it, e.g. ALN-1200.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="company_id">{{ __('Client company') }}</label>
          <select id="company_id" name="company_id" class="select2 form-select" data-placeholder="{{ __('Select company') }}" required>
            <option value="">{{ __('Select company') }}</option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="project_id">{{ __('Project') }}</label>
          <select id="project_id" name="project_id" class="select2 form-select" data-placeholder="{{ __('Select project') }}" required>
            <option value="">{{ __('Select project') }}</option>
          </select>
          <div class="form-text">{{ __('One of the selected company\'s projects.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="region">{{ __('Operational region') }}</label>
          <input type="text" id="region" name="region" class="form-control" maxlength="255" />
          <div class="form-text">{{ __('Filled in from the project\'s region when you choose it — you can change it.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-3">
          <label class="form-label" for="starts_on">{{ __('Duration from') }}</label>
          <input type="date" id="starts_on" name="starts_on" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-3">
          <label class="form-label" for="ends_on">{{ __('Duration to') }}</label>
          <input type="date" id="ends_on" name="ends_on" class="form-control" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        @if($isCreate)
          <div class="col-md-6">
            <label class="form-label d-block">{{ __('Operational status') }}</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-active" value="active" checked required>
              <label class="form-check-label" for="os-active">{{ __('Active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="operational_status" id="os-deactivated" value="deactivated" required>
              <label class="form-check-label" for="os-deactivated">{{ __('Deactivated') }}</label>
            </div>
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
        @endif

        <div class="col-md-6">
          <label class="form-label" for="attachment">{{ __('Attachment') }}</label>
          <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
          <div class="form-text">{{ __('The paper contract: a PDF or an image, up to 5 MB.') }}</div>
          <div class="small mt-1 d-none" id="po-current-attachment"></div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  {{-- FRD "اضافة خدمات التعاقد تباعا": one block per main service, with how its quota is counted. --}}
  <div class="card mb-6" id="po-services-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ __('Contract Services') }}</h5>
      <button type="button" class="btn btn-primary" id="po-add-line">
        <i class="ti ti-plus me-1"></i>{{ __('Add Service') }}
      </button>
    </div>
    <div class="card-body">
      <div id="po-lines"></div>
      <p class="text-muted mb-0" id="po-lines-empty">{{ __('No services yet. Use "Add Service" to add the first one.') }}</p>
      <div class="text-danger small mt-2 d-none" id="po-lines-feedback"></div>
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
    <a href="{{ route('app-po-list') }}" id="po-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>

{{-- One service block; __N__ is replaced by the block's number so every id (and radio group) is unique. --}}
<template id="po-line-template">
  <div class="border rounded p-4 mb-4" data-line>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h6 class="mb-0" data-line-title></h6>
      <button type="button" class="btn btn-sm btn-label-danger" data-remove-line>
        <i class="ti ti-trash me-1"></i>{{ __('Remove') }}
      </button>
    </div>
    <div class="row g-6">
      <div class="col-md-6">
        <label class="form-label" for="po-ms-__N__">{{ __('Main service') }}</label>
        <select id="po-ms-__N__" class="select2 form-select" data-field="main_service_id" data-placeholder="{{ __('Select main service') }}" required>
          <option value="">{{ __('Select main service') }}</option>
        </select>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-6">
        <label class="form-label d-block">{{ __('Quota calculation method') }}</label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="po-qm-__N__" id="po-qm-__N__-trips" value="trips_only" data-field="quota_method" checked required>
          <label class="form-check-label" for="po-qm-__N__-trips">{{ __('Trips cost only') }}</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="po-qm-__N__" id="po-qm-__N__-anc" value="trips_and_ancillary" data-field="quota_method" required>
          <label class="form-check-label" for="po-qm-__N__-anc">{{ __('Trips cost and ancillary services cost') }}</label>
        </div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="po-mt-__N__">{{ __('Max number of trips') }}</label>
        <input type="number" id="po-mt-__N__" class="form-control" data-field="max_trips" min="1" max="1000000" step="1" required />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="po-tc-__N__">{{ __('Trip cost') }}</label>
        <input type="number" id="po-tc-__N__" class="form-control" data-field="trip_cost" min="0.01" max="9999999999.99" step="0.01" required />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="po-mtc-__N__">{{ __('Maximum trips cost') }}</label>
        <input type="text" id="po-mtc-__N__" class="form-control" data-total="max_trip_cost" dir="ltr" readonly tabindex="-1" />
        <div class="form-text">{{ __('Max trips × trip cost — calculated.') }}</div>
      </div>

      <div class="col-md-4 d-none" data-ancillary>
        <label class="form-label" for="po-ac-__N__">{{ __('Ancillary services cost per trip') }}</label>
        <input type="number" id="po-ac-__N__" class="form-control" data-field="ancillary_cost_per_trip" min="0.01" max="9999999999.99" step="0.01" />
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>

      <div class="col-md-4 d-none" data-ancillary>
        <label class="form-label" for="po-mac-__N__">{{ __('Maximum ancillary services cost') }}</label>
        <input type="text" id="po-mac-__N__" class="form-control" data-total="max_ancillary" dir="ltr" readonly tabindex="-1" />
        <div class="form-text">{{ __('Max trips × ancillary cost per trip — calculated.') }}</div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="po-ap-__N__">{{ __('Expiry alert percentage') }}</label>
        <div class="input-group" dir="ltr">
          <input type="number" id="po-ap-__N__" class="form-control" data-field="alert_percent" min="1" max="99" step="1" value="20" required />
          <span class="input-group-text">%</span>
        </div>
        <div class="form-text">{{ __('Warns when the days, the trips or a cost total left fall below this share of the whole.') }}</div>
        <div class="text-danger small mt-1 d-none" data-feedback></div>
      </div>
    </div>
    <div class="text-danger small mt-3 d-none" data-line-feedback></div>
  </div>
</template>
