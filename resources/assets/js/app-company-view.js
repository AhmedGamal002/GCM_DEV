/**
 * Client Company Details — mirrors app-asset-view.js. Renders the company,
 * its contract / registration data with authorised download links for the
 * private attachments, and the (currently zero) statistics. Status changes
 * happen on the edit page (FRD V01.14 §1.11.4).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.companyViewTranslations || {};
  const id = window.companyViewId;

  const errorBox = document.getElementById('company-view-error');
  const loading = document.getElementById('company-view-loading');
  const content = document.getElementById('company-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('company-view-status');
    box.textContent = t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  const text = (elId, value) => {
    document.getElementById(elId).textContent = value == null || value === '' ? '—' : value;
  };

  function fileLink(elId, url) {
    const el = document.getElementById(elId);
    el.innerHTML = '';
    if (!url) {
      if (elId === 'cv-contract-file') el.textContent = '—';
      return;
    }
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.innerHTML = '<i class="ti ti-download"></i> ';
    link.appendChild(document.createTextNode(t.download || 'Download'));
    el.appendChild(link);
  }

  function render(c) {
    text('cv-name', c.name);
    text('cv-code', c.code);
    text('cv-prefix', c.prefix);
    text('cv-sector', c.business_sector);
    text('cv-phone', c.phone);
    text('cv-email', c.email);
    text('cv-address', c.address);

    if (c.logo_url) {
      const img = document.getElementById('cv-logo');
      img.src = c.logo_url;
      img.classList.remove('d-none');
      document.getElementById('cv-logo-fallback').classList.add('d-none');
    }

    // The map link is user input — build the anchor with DOM APIs, never innerHTML.
    if (c.location_url) {
      const holder = document.getElementById('cv-location');
      holder.textContent = '';
      const a = document.createElement('a');
      a.href = c.location_url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = c.location_url;
      holder.appendChild(a);
    }

    text('cv-contract-number', c.contract && c.contract.number);
    const from = c.contract && c.contract.start_date;
    const to = c.contract && c.contract.end_date;
    text('cv-contract-period', from || to ? (t.period || ':from → :to').replace(':from', from || '…').replace(':to', to || '…') : null);
    fileLink('cv-contract-file', c.contract && c.contract.download_url);

    text('cv-cr-number', c.commercial_registration && c.commercial_registration.number);
    fileLink('cv-cr-file', c.commercial_registration && c.commercial_registration.download_url);
    text('cv-tax-number', c.tax_registration && c.tax_registration.number);
    fileLink('cv-tax-file', c.tax_registration && c.tax_registration.download_url);

    if (c.additional_data) {
      document.getElementById('cv-additional').innerHTML = c.additional_data;
      document.getElementById('cv-additional-wrapper').classList.remove('d-none');
    }

    if (c.updated_by_name && t.last_updated_by) {
      const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
      document.getElementById('cv-updated-by').textContent = t.last_updated_by
        .replace(':name', c.updated_by_name)
        .replace(':at', at);
    }

    const badge = document.getElementById('cv-status');
    const s = statusObj[c.operational_status] || { title: c.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    Object.entries(c.stats || {}).forEach(([key, val]) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = val;
    });

    document.getElementById('cv-edit-link').href = (t.edit_url_base || '/app/company/edit') + '/' + c.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/companies/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));
});
