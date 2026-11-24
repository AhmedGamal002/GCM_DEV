/**
 * The driver's trip details (FRD V01.14 §1.14, "الاطلاع على عرض بيانات الرحلة ومستند DN فقط", part 7.6) —
 * GET /api/v1/trips/{id}, which sends only what a driver may see (no costs, no PO number, no history). Plus his one
 * change after creating (ج13): adding / replacing the recycle receipt photo until the trip is completed
 * (`driver_actions.attach_recycle_receipt`). The generated delivery note document comes with the documents part.
 * Every value is set as text.
 */
'use strict';

import { renderTripDocuments } from './trip/documents-card.js';

$(function () {
  const cfg = window.driverTripViewConfig;
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

  function showError(box, error) {
    const data = error && error.response && error.response.data;
    const first = data && data.errors ? Object.values(data.errors)[0] : null;
    box.textContent = (Array.isArray(first) ? first[0] : first) || (data && data.message) || window.gcmErrorMessage(error);
    box.classList.remove('d-none');
  }

  function render(trip) {
    set('code', trip.code);
    const badge = $id('dv-status-badge');
    badge.className = 'badge ' + (statusClass[trip.status] || 'bg-label-secondary');
    badge.textContent = trip.status_label;

    set('date', trip.trip_at ? moment(trip.trip_at).format('YYYY-MM-DD HH:mm') : '');
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

    // the location link was built by the widget; shown as an element, only if it is an http(s) link
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

    const files = trip.files || {};
    $id('dv-photo').innerHTML = '';
    if (files['live-photo']) {
      const img = document.createElement('img');
      img.src = files['live-photo'];
      img.alt = '';
      img.className = 'img-fluid rounded border';
      img.style.maxHeight = '320px';
      $id('dv-photo').appendChild(img);
    } else {
      $id('dv-photo').textContent = '—';
    }

    $id('dv-receipt-current').innerHTML = files['recycle-receipt'] ? window.gcmViewAttachment(files['recycle-receipt']) : '—';
    renderTripDocuments(trip, { card: $id('dv-generated-card'), list: $id('dv-generated'), hint: $id('dv-generated-hint'), exportButton: null, labels: { delivery_note: t.delivery_note, waste_manifest: t.waste_manifest } });

    const may = !!(trip.driver_actions && trip.driver_actions.attach_recycle_receipt);
    $id('dv-receipt-form').classList.toggle('d-none', !may);
    $id('dv-receipt-closed').classList.toggle('d-none', may);
  }

  function load() {
    return window.axios
      .get(cfg.apiBase + '/' + cfg.id)
      .then((response) => render(response.data.data))
      .catch((error) => showError($id('dv-error'), error));
  }

  $id('dv-receipt-upload').addEventListener('click', async function () {
    const error = $id('dv-receipt-error');
    error.classList.add('d-none');
    $id('dv-error').classList.add('d-none');

    const file = $id('dv-receipt-file').files[0];
    if (!file) {
      error.textContent = t.choose_file;
      error.classList.remove('d-none');

      return;
    }

    const tooLarge = window.gcmFileGuard($id('dv-receipt-form').parentElement, cfg.limits);
    if (tooLarge) {
      error.textContent = tooLarge;
      error.classList.remove('d-none');

      return;
    }

    const data = new FormData();
    data.append('recycle_receipt', file);
    window.gcmBusy.start({ progress: true });

    try {
      const response = await window.axios.post(cfg.receiptUrl, data, { headers: { 'Content-Type': 'multipart/form-data' }, onUploadProgress: window.gcmBusy.onUploadProgress });
      window.gcmBusy.stop();
      $id('dv-receipt-file').value = '';
      $id('dv-flash').textContent = t.receipt_saved;
      $id('dv-flash').classList.remove('d-none');
      render(response.data.data);
    } catch (e) {
      window.gcmBusy.stop();
      showError(error, e); // a 409 (the trip completed meanwhile) or no connection: the message says so, nothing is lost
    }
  });

  load();
});
