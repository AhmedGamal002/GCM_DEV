{{-- FRD V01.14 §1.3 / §1.5 / §1.7 "التبعية": belongs to GCM, or to a contractor — choosing a contractor
     shows the list of contractors. Wired by resources/js/gcm-affiliation.js (window.gcmAffiliation(form)).
     Goes inside a `.row`; the page loads select2 like any other form select. --}}
<div class="col-md-6">
  <label class="form-label d-block">{{ __('Affiliation') }}</label>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="radio" name="affiliation" id="aff-gcm" value="gcm" checked required>
    <label class="form-check-label" for="aff-gcm">{{ __('Belongs to GCM') }}</label>
  </div>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="radio" name="affiliation" id="aff-contractor" value="contractor" required>
    <label class="form-check-label" for="aff-contractor">{{ __('Belongs to a Contractor') }}</label>
  </div>
  <div class="text-danger small mt-1 d-none" data-feedback></div>
</div>

<div class="col-md-6 d-none" data-affiliation-contractor>
  <label class="form-label" for="contractor_id">{{ __('Contractor') }}</label>
  <select id="contractor_id" name="contractor_id" class="select2 form-select" data-placeholder="{{ __('Select contractor') }}" disabled required>
    <option value=""></option>
  </select>
  <div class="text-danger small mt-1 d-none" data-feedback></div>
</div>
