/**
 * Purchase Order Details (FRD V01.14 §1.13 "صفحة عرض تفاصيل تعاقد") — mirrors app-project-view.js. Renders the PO
 * and, per service ("بند"), its quota and what is left of each part: the days, the trips, the trip cost and — for
 * a method that counts it — the ancillary cost, each as a bar coloured by its state. Every state and label is
 * computed and translated by the server (PurchaseOrderStatus); this file only paints it. Names are user input,
 * so everything is built with DOM APIs (textContent), never innerHTML.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.poViewTranslations || {};
  const id = window.poViewId;

  const errorBox = document.getElementById('po-view-error');
  const loading = document.getElementById('po-view-loading');
  const content = document.getElementById('po-view-content');

  if (new URLSearchParams(window.location.search).get('saved')) {
    const box = document.getElementById('po-view-status');
    box.textContent = t.saved;
    box.classList.remove('d-none');
  }

  const accountStatus = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const itemTone = { valid: 'bg-label-success', near_exhaustion: 'bg-label-warning', exhausted: 'bg-label-danger' };
  const barTone = { ok: 'bg-success', near: 'bg-warning', exhausted: 'bg-danger' };
  const componentLabel = {
    date: t.component_date || 'Days',
    trips: t.component_trips || 'Trips',
    trip_cost: t.component_trip_cost || 'Trip cost',
    ancillary_cost: t.component_ancillary_cost || 'Ancillary services cost'
  };

  const num = (n, digits) => Number(n).toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || (window.gcmI18n && window.gcmI18n.unknown);
    errorBox.classList.remove('d-none');
  }

  const text = (elId, value) => {
    document.getElementById(elId).textContent = value == null || value === '' ? '—' : value;
  };

  function link(holderId, href, label) {
    const holder = document.getElementById(holderId);
    holder.textContent = '';
    if (!href) {
      holder.textContent = label || '—';
      return;
    }
    const a = el('a', null, label);
    a.href = href;
    holder.appendChild(a);
  }

  /** One component of a service: a label, "used of max" and a bar of what is left. */
  function componentRow(key, c, currency) {
    const money = key === 'trip_cost' || key === 'ancillary_cost';
    const fmt = (n) => (money ? num(n, 2) + ' ' + currency : num(n, 0));

    const row = el('div', 'mb-4');
    const head = el('div', 'd-flex justify-content-between mb-1');
    head.appendChild(el('span', 'fw-medium', componentLabel[key] || key));
    head.appendChild(el('small', 'text-muted', `${t.used || 'Used'}: ${fmt(c.used)} ${t.of || 'of'} ${fmt(c.max)}`));
    row.appendChild(head);

    const bar = el('div', 'progress');
    bar.style.height = '8px';
    const fill = el('div', 'progress-bar ' + (barTone[c.state] || 'bg-success'));
    fill.setAttribute('role', 'progressbar');
    fill.style.width = Math.max(0, Math.min(100, c.percent_remaining)) + '%';
    bar.appendChild(fill);
    row.appendChild(bar);

    row.appendChild(el('small', 'text-muted', `${t.remaining || 'Remaining'}: ${fmt(c.remaining)} (${c.percent_remaining}%)`));
    return row;
  }

  function serviceCard(line, currency) {
    const card = el('div', 'card mb-4');

    const header = el('div', 'card-header d-flex justify-content-between align-items-center flex-wrap gap-2');
    const title = el('div');
    title.appendChild(el('h5', 'mb-1', line.main_service ? line.main_service.name : '—'));
    title.appendChild(el('small', 'text-muted', t[line.quota_method] || line.quota_method));
    header.appendChild(title);
    header.appendChild(el('span', 'badge ' + (itemTone[line.status] || 'bg-label-secondary'), line.status_label || ''));
    card.appendChild(header);

    const body = el('div', 'card-body');

    const facts = el('div', 'row g-3 mb-4');
    const fact = (label, value) => {
      const col = el('div', 'col-sm-6 col-md-4');
      col.appendChild(el('div', 'text-muted small', label));
      const v = el('div', 'fw-medium', value);
      v.setAttribute('dir', 'ltr');
      col.appendChild(v);
      facts.appendChild(col);
    };
    fact(t.max_trips || 'Max number of trips', num(line.max_trips, 0));
    fact(t.trip_cost || 'Trip cost', num(line.trip_cost, 2) + ' ' + currency);
    fact(t.max_trip_cost || 'Maximum trips cost', num(line.max_trip_cost, 2) + ' ' + currency);
    if (line.ancillary_cost_per_trip != null) {
      fact(t.ancillary_cost_per_trip || 'Ancillary services cost per trip', num(line.ancillary_cost_per_trip, 2) + ' ' + currency);
      fact(t.max_ancillary_cost || 'Maximum ancillary services cost', num(line.max_ancillary_cost, 2) + ' ' + currency);
    }
    fact(t.alert_percent || 'Expiry alert percentage', line.alert_percent + '%');
    body.appendChild(facts);

    ['date', 'trips', 'trip_cost', 'ancillary_cost'].forEach((key) => {
      if (line.components && line.components[key]) body.appendChild(componentRow(key, line.components[key], currency));
    });

    card.appendChild(body);
    return card;
  }

  function render(po) {
    const currency = po.currency || '';

    text('pov-number', po.number);
    text('pov-number-2', po.number);
    text('pov-region', po.region);
    text('pov-duration', `${po.starts_on} → ${po.ends_on}`);
    text('pov-days', po.days_label);
    text('pov-items-label', po.status_label);

    link('pov-company', po.company && t.can_open_company ? `${t.company_view_url_base}/${po.company.id}` : null, po.company && po.company.name);
    link('pov-project', po.project && t.can_open_project ? `${t.project_view_url_base}/${po.project.id}` : null, po.project && po.project.name);

    const account = accountStatus[po.operational_status] || { title: po.operational_status, class: 'bg-label-secondary' };
    const accountBadge = document.getElementById('pov-account-status');
    accountBadge.textContent = account.title;
    accountBadge.className = 'badge ' + account.class;

    const itemsBadge = document.getElementById('pov-items-status');
    itemsBadge.textContent = po.status_label;
    itemsBadge.className = 'badge ' + (itemTone[po.status] || 'bg-label-secondary');

    // The paper contract sits on the private disk behind a gate-checked route.
    if (po.attachment && po.attachment.download_url) {
      const holder = document.getElementById('pov-attachment');
      holder.textContent = '';
      const a = el('a');
      a.href = po.attachment.download_url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.appendChild(el('i', 'ti ti-download'));
      a.appendChild(document.createTextNode(' ' + (t.download || 'Download')));
      holder.appendChild(a);
    }

    if (po.additional_data) {
      document.getElementById('pov-additional').innerHTML = po.additional_data;
      document.getElementById('pov-additional-wrapper').classList.remove('d-none');
    }

    if (po.updated_by_name && t.last_updated_by) {
      const at = po.updated_at ? new Date(po.updated_at).toLocaleString() : '';
      document.getElementById('pov-updated-by').textContent = t.last_updated_by.replace(':name', po.updated_by_name).replace(':at', at);
    }

    // Statistics: trips and cost from the ledger; the waste moved from the trips under this contract.
    const totals = po.totals || {};
    const set = (key, value, sub) => {
      document.querySelector(`[data-stat="${key}"]`).textContent = value;
      document.querySelector(`[data-stat-sub="${key}"]`).textContent = sub || '';
    };
    set('trips', num(totals.trips_used || 0, 0), `${t.of || 'of'} ${num(totals.max_trips || 0, 0)}`);
    set('cost', num(totals.cost_used || 0, 2), `${t.of || 'of'} ${num(totals.max_cost || 0, 2)} ${currency}`);
    set('waste', num(totals.waste_tons || 0, 3), '');

    const services = document.getElementById('pov-services');
    services.textContent = '';
    if (!(po.services || []).length) {
      services.appendChild(el('div', 'alert alert-info', t.no_services || 'This purchase order has no services.'));
    }
    (po.services || []).forEach((line) => services.appendChild(serviceCard(line, currency)));

    const editLink = document.getElementById('pov-edit-link');
    if (editLink) editLink.href = (t.edit_url_base || '/app/po/edit') + '/' + po.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/purchase-orders/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));
});
