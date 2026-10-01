/**
 * Shared engine for the create & edit sub-service forms (both render
 * `tenant.sub-services._form`). FRD V01.14 §1.9.2:
 *  - the main service is a live-search picker of ACTIVE main services on
 *    create (`?main_service_id=` preselects), and read-only on edit;
 *  - its supported vehicle categories show automatically, plus — as
 *    information only — the embedded containers of vehicles in those
 *    categories;
 *  - the capacities shown depend on the chosen asset type (a capacity
 *    that applies to "both" fits either);
 *  - facilities are required unless the main service is a supplying one;
 *    only active facilities are offered (plus any already linked, on edit).
 * The whole form turns read-only when the sub-service is locked.
 */
'use strict';

import { createFormErrors, renderCheckboxes, checkedValues, setLastUpdated, escapeHtml } from './form-helpers.js';

export function initSubServiceForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.subServiceFormTranslations || {};

  const form = document.getElementById('subServiceForm');
  if (!form) return;

  const errorBox = document.getElementById('sub-service-form-error');
  const loading = document.getElementById('sub-service-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const capacitiesBox = document.getElementById('capacities-box');
  const facilitiesBox = document.getElementById('facilities-box');
  const categoriesBox = document.getElementById('supported-categories');
  const embeddedInfo = document.getElementById('embedded-info');
  const embeddedList = document.getElementById('embedded-list');
  const optionalNote = document.getElementById('facilities-optional-note');
  const errors = createFormErrors(form, errorBox, t);

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  let capacities = [];
  let facilities = [];
  let linkedFacilityIds = [];
  let currentMain = null; // {id, service_type, ...}

  const fmt = (n) => (n == null ? '' : Number(n).toString());

  // ---------------------------------------------------------- dependent UI
  function renderCapacities() {
    const type = (form.querySelector('input[name="asset_type"]:checked') || {}).value;
    if (!type) {
      capacitiesBox.innerHTML = '<div class="col-12 text-muted small">' + escapeHtml(t.choose_type_first) + '</div>';
      return;
    }
    const keep = checkedValues(form, 'capacity_ids');
    const fitting = capacities.filter((c) => c.applies_to === 'both' || c.applies_to === type);
    renderCheckboxes(
      capacitiesBox,
      'capacity_ids',
      fitting.map((c) => ({
        value: c.id,
        label: c.name,
        hint: `${fmt(c.capacity_cbm)} ${t.cbm || 'CBM'} / ${fmt(c.capacity_ton)} ${t.ton || 'TON'}`
      })),
      opts.initialCapacityIds && !keep.length ? opts.initialCapacityIds : keep,
      t.no_capacities
    );
    opts.initialCapacityIds = null;
  }

  function renderFacilities(checked) {
    const offered = facilities.filter((f) => f.operational_status === 'active' || linkedFacilityIds.includes(f.id));
    renderCheckboxes(
      facilitiesBox,
      'facility_ids',
      offered.map((f) => ({
        value: f.id,
        label: f.name,
        hint: (t.services && t.services[f.environmental_service]) || f.environmental_service
      })),
      checked,
      t.no_facilities
    );
  }

  function showMainDetails(main, embedded) {
    currentMain = main;
    const cats = main.vehicle_categories || [];
    categoriesBox.innerHTML = cats.length
      ? cats.map((c) => '<span class="badge bg-label-primary">' + escapeHtml(c.name) + '</span>').join('')
      : '<small class="text-muted">—</small>';

    optionalNote.classList.toggle('d-none', main.service_type !== 'supplying');

    const rows = embedded || [];
    embeddedList.innerHTML = rows
      .map(
        (e) =>
          '<li>' + escapeHtml(e.vehicle_category) + ' — ' + escapeHtml((t.asset_types && t.asset_types[e.asset_type]) || e.asset_type) +
          ' ' + escapeHtml(e.capacity_name) + ' (' + fmt(e.capacity_cbm) + ' ' + escapeHtml(t.cbm || 'CBM') + ' / ' +
          fmt(e.capacity_ton) + ' ' + escapeHtml(t.ton || 'TON') + ') · ' +
          escapeHtml((t.vehicles_count || ':count vehicles').replace(':count', e.vehicles)) + '</li>'
      )
      .join('');
    embeddedInfo.classList.toggle('d-none', !rows.length);
  }

  function loadMain(mainId) {
    if (!mainId) {
      currentMain = null;
      categoriesBox.innerHTML = '<small class="text-muted">' + escapeHtml(t.from_main_service || '') + '</small>';
      embeddedInfo.classList.add('d-none');
      optionalNote.classList.add('d-none');
      return Promise.resolve();
    }
    return window.axios.get(`/api/v1/main-services/${mainId}`).then((res) => {
      showMainDetails(res.data.data, res.data.meta && res.data.meta.embedded_capacities);
    });
  }

  form.querySelectorAll('input[name="asset_type"]').forEach((r) => r.addEventListener('change', renderCapacities));

  // ----------------------------------------------------- client validation
  function validate() {
    errors.clearAll();
    let first = null;
    const fail = (el, msg) => {
      errors.show(el, msg);
      if (!first) first = el;
    };

    const name = form.querySelector('#name');
    if (!name.value.trim()) fail(name, t.required);

    const mainSelect = form.querySelector('#main_service_id');
    if (mainSelect && !mainSelect.value) fail(mainSelect, t.required);

    if (!form.querySelector('input[name="asset_type"]:checked')) fail(form.querySelector('input[name="asset_type"]'), t.required);
    else if (!checkedValues(form, 'capacity_ids').length) fail(errors.targetFor('capacity_ids'), t.choose_one_capacity);

    const supplying = currentMain && currentMain.service_type === 'supplying';
    if (!supplying && !checkedValues(form, 'facility_ids').length) fail(errors.targetFor('facility_ids'), t.choose_one_facility);

    if (first && first.scrollIntoView) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return !first;
  }

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    const color = form.querySelector('input[name="skip_color_code"]:checked');
    const data = {
      name: form.querySelector('#name').value.trim(),
      asset_type: form.querySelector('input[name="asset_type"]:checked').value,
      capacity_ids: checkedValues(form, 'capacity_ids'),
      facility_ids: checkedValues(form, 'facility_ids'),
      requires_recycle_receipt: form.querySelector('#requires_recycle_receipt').checked,
      skip_color_code: color && color.value ? color.value : null,
      additional_data: quill.getLength() > 1 ? quill.root.innerHTML : null
    };
    if (mode === 'create') {
      data.main_service_id = form.querySelector('#main_service_id').value;
      data.operational_status = form.querySelector('input[name="operational_status"]:checked').value;
    }

    submitBtn.disabled = true;
    window.gcmBusy.start();

    const request = mode === 'edit' ? window.axios.patch(`/api/v1/sub-services/${id}`, data) : window.axios.post('/api/v1/sub-services', data);

    request
      .then((res) => {
        const target = mode === 'edit' ? id : res.data.data.id;
        window.location.href = `${t.view_url_base || '/app/sub-service/view'}/${target}?${mode === 'edit' ? 'saved' : 'created'}=1`;
      })
      .catch((error) => {
        window.gcmBusy.stop();
        submitBtn.disabled = false;
        errors.handleFailure(error);
      });
  });

  // --------------------------------------------------------- mode bootstrap
  const referenceData = Promise.all([
    window.axios.get('/api/v1/asset-capacity-categories').then((res) => res.data.data),
    window.gcmFetchAll('/api/v1/facilities', { sort_by: 'name' })
  ]).then(([caps, facs]) => {
    capacities = caps;
    facilities = facs;
  });

  const fail = (error) => {
    if (loading) loading.classList.add('d-none');
    errors.showMessage(error.response && error.response.data && error.response.data.message);
  };

  if (mode === 'create') {
    const mainSelect = form.querySelector('#main_service_id');
    // A change made through Select2 is a jQuery event, not a native one.
    window.$(mainSelect).on('change', () => {
      errors.clear(mainSelect);
      loadMain(mainSelect.value).catch(fail);
    });
    window.initGcmSelects(form);

    const typeLabels = t.service_types || {};
    Promise.all([referenceData, window.gcmFetchAll('/api/v1/main-services', { operational_status: 'active', sort_by: 'name' })])
      .then(([, mains]) => {
        renderCapacities();
        renderFacilities([]);

        mainSelect.innerHTML = '<option value=""></option>';
        mains.forEach((m) => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = `${m.name} (${typeLabels[m.service_type] || m.service_type})`;
          mainSelect.appendChild(opt);
        });

        const preselected = new URLSearchParams(window.location.search).get('main_service_id');
        if (preselected && mains.some((m) => String(m.id) === preselected)) {
          mainSelect.value = preselected;
        }
        window.refreshGcmSelect(mainSelect);
        if (mainSelect.value) return loadMain(mainSelect.value);
      })
      .catch(fail);
    return;
  }

  const cancel = document.getElementById('sub-service-form-cancel');
  if (cancel) {
    cancel.href = `${t.view_url_base || '/app/sub-service/view'}/${id}`;
    if (t.cancel) cancel.textContent = t.cancel;
  }

  Promise.all([window.axios.get(`/api/v1/sub-services/${id}`), referenceData])
    .then(([res]) => {
      const sub = res.data.data;

      form.querySelector('#name').value = sub.name || '';
      form.querySelector('#main_service_name').value = sub.main_service ? sub.main_service.name : '';
      const type = form.querySelector(`input[name="asset_type"][value="${sub.asset_type}"]`);
      if (type) type.checked = true;
      form.querySelector('#requires_recycle_receipt').checked = !!sub.requires_recycle_receipt;
      const color = form.querySelector(`input[name="skip_color_code"][value="${sub.skip_color_code || ''}"]`);
      if (color) color.checked = true;
      quill.root.innerHTML = sub.additional_data || '';

      opts.initialCapacityIds = (sub.capacities || []).map((c) => c.id);
      renderCapacities();
      linkedFacilityIds = (sub.facilities || []).map((f) => f.id);
      renderFacilities(linkedFacilityIds);
      setLastUpdated('sub-service-last-updated', sub, t);

      return loadMain(sub.main_service_id).then(() => sub);
    })
    .then((sub) => {
      if (sub.locked) {
        document.getElementById('sub-service-locked').classList.remove('d-none');
        form.querySelectorAll('input, button[type="submit"]').forEach((el) => (el.disabled = true));
        quill.enable(false);
      }

      loading.classList.add('d-none');
      form.classList.remove('d-none');
      onLoaded(sub);
    })
    .catch(fail);
}
