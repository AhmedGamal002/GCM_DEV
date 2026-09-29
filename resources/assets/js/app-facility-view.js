/**
 * Facility Details — mirrors app-asset-view.js. Renders the facility, its
 * contract, the (for now empty) list of sub-services that accept it, and
 * the "Facility Status" card (confirm-checkbox-gates-the-button).
 *
 * The Edit button and the status card are for (system admin / data entry)
 * only — the Blade shell passes `facilityViewCanManage`, so the auditor
 * sees the page read-only. The API's policy remains the real guard.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.facilityViewTranslations || {};
  const id = window.facilityViewId;
  const canManage = !!window.facilityViewCanManage;

  const errorBox = document.getElementById('facility-view-error');
  const loading = document.getElementById('facility-view-loading');
  const content = document.getElementById('facility-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved') || query.get('status_updated')) {
    const box = document.getElementById('facility-view-status');
    box.textContent = query.get('status_updated') ? t.status_updated : t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const serviceLabels = {
    disposal: t.disposal,
    sewage_treatment: t.sewage_treatment,
    recycle: t.recycle
  };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  function esc(v) {
    const d = document.createElement('div');
    d.textContent = v == null ? '' : v;
    return d.innerHTML;
  }

  function render(f) {
    document.getElementById('fv-name').textContent = f.name;
    document.getElementById('fv-prefix').textContent = f.prefix;
    document.getElementById('fv-service').textContent = serviceLabels[f.environmental_service] || f.environmental_service;

    if (f.environmental_service === 'recycle' && f.recycling_efficiency !== null) {
      document.getElementById('fv-efficiency').textContent = f.recycling_efficiency + '%';
      document.getElementById('fv-efficiency-row').classList.remove('d-none');
    }

    document.getElementById('fv-address').textContent = f.address || '—';
    if (f.location_url) {
      const map = document.getElementById('fv-map');
      map.href = f.location_url;
      map.textContent = t.open_map || f.location_url;
      document.getElementById('fv-map-row').classList.remove('d-none');
    }

    if (f.logo_url) {
      const logo = document.getElementById('fv-logo');
      logo.src = f.logo_url;
      logo.classList.remove('d-none');
      document.getElementById('fv-logo-fallback').classList.add('d-none');
    }

    document.getElementById('fv-contract-number').textContent = f.contract_number || '—';
    document.getElementById('fv-contract-start').textContent = f.contract_start || '—';
    document.getElementById('fv-contract-end').textContent = f.contract_end || '—';
    if (f.has_contract_attachment) {
      const link = document.getElementById('fv-contract-file');
      link.href = `${t.contract_url_base || '/api/v1/facilities'}/${f.id}/contract`;
      link.querySelector('span').textContent = t.download_contract || 'Download contract';
      document.getElementById('fv-contract-file-row').classList.remove('d-none');
    }

    if (f.additional_data) {
      document.getElementById('fv-additional').innerHTML = f.additional_data;
      document.getElementById('fv-additional-wrapper').classList.remove('d-none');
    }

    if (f.updated_by_name && t.last_updated_by) {
      const at = f.updated_at ? new Date(f.updated_at).toLocaleString() : '';
      document.getElementById('fv-updated-by').textContent = t.last_updated_by
        .replace(':name', f.updated_by_name)
        .replace(':at', at);
    }

    const badge = document.getElementById('fv-status');
    const s = statusObj[f.operational_status] || { title: f.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    const edit = document.getElementById('fv-edit-link');
    edit.href = (t.edit_url_base || '/app/facility/edit') + '/' + f.id;
    if (canManage) edit.classList.remove('d-none');

    // Sub-services that list this facility (empty until the Services module exists).
    const subs = f.supported_sub_services || [];
    const subsBox = document.getElementById('fv-sub-services');
    const subsEmpty = document.getElementById('fv-sub-services-empty');
    if (subs.length) {
      subsBox.innerHTML = subs.map((s) => '<span class="badge bg-label-primary">' + esc(s.name) + '</span>').join('');
    } else {
      subsEmpty.textContent = t.no_sub_services || '';
      subsEmpty.classList.remove('d-none');
    }

    const chosen = document.querySelector(`#facilityStatusForm input[name="new_status"][value="${f.operational_status}"]`);
    if (chosen) chosen.checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    if (canManage) document.getElementById('facility-status-card').classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/facilities/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  // Status card
  const statusForm = document.getElementById('facilityStatusForm');
  const confirmCheckbox = document.getElementById('fs-confirm');
  const submitBtn = document.getElementById('fs-submit');

  confirmCheckbox.addEventListener('change', function () {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const checked = statusForm.querySelector('input[name="new_status"]:checked');
    if (!checked) return;

    window.axios
      .patch(`/api/v1/facilities/${id}/status`, { status: checked.value })
      .then(() => {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch(function (error) {
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
