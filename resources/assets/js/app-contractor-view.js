/**
 * Contractor Details — mirrors app-company-view.js. Renders the contractor,
 * its certified services, its contract / registration data with authorised
 * download links for the private attachments, and the counts of the drivers,
 * vehicles, containers and tanks it supplies. Status changes happen on the
 * edit page (FRD V01.14 §1.10.4).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.contractorViewTranslations || {};
  const id = window.contractorViewId;

  const errorBox = document.getElementById('contractor-view-error');
  const loading = document.getElementById('contractor-view-loading');
  const content = document.getElementById('contractor-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('contractor-view-status');
    box.textContent = t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || (window.gcmI18n && window.gcmI18n.unknown);
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

  // FRD §1.10.2: no certified services ticked means the contractor supports all of them.
  function renderServices(c) {
    const holder = document.getElementById('cv-services');
    holder.innerHTML = '';

    if (c.supports_all_services) {
      const badge = document.createElement('span');
      badge.className = 'badge bg-label-primary';
      badge.textContent = t.all_services || 'All services';
      holder.appendChild(badge);
      return;
    }

    (c.certified_services || []).forEach((s) => {
      const badge = document.createElement('span');
      badge.className = 'badge me-1 mb-1 ' + (s.operational_status === 'active' ? 'bg-label-info' : 'bg-label-secondary');
      badge.textContent = s.name; // user input — textContent, never innerHTML
      holder.appendChild(badge);
    });
  }

  function render(c) {
    text('cv-name', c.name);
    text('cv-trading-name', c.trading_name);
    document.getElementById('cv-trading-name').classList.toggle('d-none', !c.trading_name);
    text('cv-code', c.code);
    text('cv-prefix', c.prefix);
    text('cv-representative', c.representative && c.representative.name);
    text('cv-phone', c.phone);
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

    renderServices(c);

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

    ['drivers_count', 'vehicles_count', 'containers_count', 'tanks_count'].forEach((key) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = c[key] || 0;
    });

    if (t.can_manage) {
      const edit = document.getElementById('cv-edit-link');
      edit.href = (t.edit_url_base || '/app/contractor/edit') + '/' + c.id;
      edit.classList.remove('d-none');
    }

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/contractors/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));
});
