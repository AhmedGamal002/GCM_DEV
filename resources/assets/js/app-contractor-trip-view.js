/**
 * The contractor user's trip details (FRD V01.14 §1.14, part 7.7) — read only. GET /api/v1/trips/{id} sends a
 * field-side user only what he may see (ج18: no costs, no PO number, no history, no photos or files), and the
 * delivery note document once the trip is completed (8.1). A trip of another contractor is a 404 there. Every value
 * is set as text.
 */
'use strict';

import { renderTripDocuments } from './trip/documents-card.js';

$(function () {
  const cfg = window.contractorTripViewConfig;
  const t = cfg.t;
  const $id = (x) => document.getElementById(x);
  const set = (key, value) => document.querySelectorAll('[data-v="' + key + '"]').forEach((n) => (n.textContent = value === null || value === undefined || value === '' ? '—' : value));

  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };
  const entity = (e) => (!e ? '' : (e.name || e.plate) + (e.affiliation ? ' — ' + (e.affiliation === 'gcm' ? t.gcm : e.entity_name) : ''));
  const capacity = (c) => (c ? c.name + ' · ' + c.capacity_ton + ' ' + t.ton + ' / ' + c.capacity_cbm + ' ' + t.cbm : '');

  function render(trip) {
    set('code', trip.code);
    const badge = $id('cv-status-badge');
    badge.className = 'badge ' + (statusClass[trip.status] || 'bg-label-secondary');
    badge.textContent = trip.status_label;

    set('date', trip.trip_at ? moment(trip.trip_at).format('YYYY-MM-DD HH:mm') : '');
    set('driver', entity(trip.driver));
    set('vehicle', entity(trip.vehicle));
    set('vehicle_type', trip.vehicle && trip.vehicle.category && trip.vehicle.category.name);
    set('going', entity(trip.going_asset));
    set('returning', entity(trip.returning_asset));
    set('company', trip.company && trip.company.name);
    set('project', trip.project && trip.project.name);
    set('main_service', trip.main_service && trip.main_service.name);
    set('sub_service', trip.sub_service && trip.sub_service.name);
    set('capacity', capacity(trip.capacity));
    const supplying = trip.main_service && trip.main_service.service_type === 'supplying';
    document.querySelector('[data-row="quantity"]').classList.toggle('d-none', supplying);
    set(
      'quantity',
      trip.quantity_ton === null && trip.quantity_cbm === null
        ? ''
        : [trip.quantity_ton !== null ? trip.quantity_ton + ' ' + t.ton : null, trip.quantity_cbm !== null ? trip.quantity_cbm + ' ' + t.cbm : null].filter(Boolean).join(' / ')
    );
    set('facility', trip.facility && trip.facility.name);
    set('delivery_note', trip.delivery_note_number);
    set('updated', trip.updated_by ? trip.updated_by.name + (trip.updated_at ? ' — ' + moment(trip.updated_at).format('YYYY-MM-DD HH:mm') : '') : '');

    // the location link: shown as an element, only if it is an http(s) link
    document.querySelectorAll('[data-v="location"]').forEach((n) => {
      n.textContent = '—';
      if (trip.location_url && /^https?:\/\//i.test(trip.location_url)) {
        const a = document.createElement('a');
        a.href = trip.location_url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = t.open_map;
        n.textContent = '';
        n.appendChild(a);
      }
    });

    renderTripDocuments(trip, { card: $id('cv-generated-card'), list: $id('cv-generated'), hint: $id('cv-generated-hint'), exportButton: null, labels: { delivery_note: t.delivery_note, waste_manifest: t.waste_manifest } });
  }

  window.axios
    .get(cfg.apiBase + '/' + cfg.id)
    .then((response) => render(response.data.data))
    .catch((error) => {
      const box = $id('cv-error');
      box.textContent = (error.response && error.response.data && error.response.data.message) || window.gcmErrorMessage(error);
      box.classList.remove('d-none');
    });
});
