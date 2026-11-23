/**
 * The client's trip details (FRD V01.14 §1.14, the "العميل" details page, part 7.5) — GET /api/v1/trips/{id},
 * which sends only what this account may see (ج18: the PO number to the project manager only; no costs, no
 * facility, no document numbers, no history). Every value is escaped / set as text.
 *
 * Buttons (FRD): "Approve the trip" — straight from here when the account has a saved signature (the legal
 * confirmation, then the e-mailed code; its saved signature and stamp are used), otherwise it opens the review
 * page to sign; "Edit" — the review page (sub-service, signature, stamp, attachment, comment, approve / reject);
 * "Back". Both only while the trip awaits the client and the account may approve it (`client_actions`).
 */
'use strict';

import { STATUS_CLASS, confirmBox, submitReview } from './client-trip/review-core.js';

$(function () {
  const t = window.clientTripViewTranslations || {};
  const id = window.clientTripViewId;
  const $id = (x) => document.getElementById(x);
  const setText = (el, value) => ($id(el).textContent = value === null || value === undefined || value === '' ? '—' : value);

  const flash = new URLSearchParams(window.location.search).get('done');
  if (flash === 'approved' || flash === 'rejected') {
    $id('ctv-status').textContent = flash === 'approved' ? t.approved_flash : t.rejected_flash;
    $id('ctv-status').classList.remove('d-none');
  }

  const fileLink = (holder, url) => {
    const el = $id(holder);
    el.innerHTML = url ? window.gcmViewAttachment(url) : '—';
  };

  function render(trip) {
    setText('ctv-code', trip.code);
    setText('ctv-code-2', trip.code);
    const badge = $id('ctv-status-badge');
    badge.className = 'badge ' + (STATUS_CLASS[trip.status] || 'bg-label-secondary');
    badge.textContent = trip.status_label;
    setText('ctv-date', trip.trip_at ? moment(trip.trip_at).format('YYYY-MM-DD HH:mm') : '');
    setText('ctv-driver', trip.driver && trip.driver.name);
    setText('ctv-vehicle', trip.vehicle && trip.vehicle.plate);

    setText('ctv-company', trip.company && trip.company.name);
    setText('ctv-project', trip.project && trip.project.name);
    setText('ctv-representative', trip.project && trip.project.representative ? trip.project.representative.name : '');

    // The project's location link (validated http/https on save) — built as an element, never as HTML text.
    const loc = $id('ctv-location');
    loc.textContent = '—';
    const url = trip.project && trip.project.location_url;
    if (url && /^https?:\/\//i.test(url)) {
      const a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = t.open_map;
      loc.textContent = '';
      loc.appendChild(a);
    }

    // ج18: the manager sees the PO number; the auditor whether there is one.
    if (trip.purchase_order) setText('ctv-po', trip.purchase_order.number);
    else setText('ctv-po', trip.linked_to_purchase_order ? t.linked : t.not_linked);

    setText('ctv-main-service', trip.main_service && trip.main_service.name);
    setText('ctv-sub-service', trip.sub_service && trip.sub_service.name);
    setText(
      'ctv-quantity',
      trip.quantity_ton === null && trip.quantity_cbm === null
        ? ''
        : [trip.quantity_ton !== null ? trip.quantity_ton + ' ' + t.ton : null, trip.quantity_cbm !== null ? trip.quantity_cbm + ' ' + t.cbm : null]
            .filter(Boolean)
            .join(' / ')
    );

    const files = trip.files || {};
    fileLink('ctv-photo', files['live-photo']);
    setText('ctv-comment', trip.client_comment);
    fileLink('ctv-attachment', files['client-attachment']);

    if (trip.updated_by && trip.updated_at) {
      $id('ctv-updated-by').textContent = t.last_updated_by
        .replace(':name', trip.updated_by.name)
        .replace(':at', moment(trip.updated_at).format('YYYY-MM-DD HH:mm'));
    }

    const may = trip.client_actions && trip.client_actions.approve;
    $id('ctv-approve').classList.toggle('d-none', !may);
    $id('ctv-review').classList.toggle('d-none', !may);

    $id('ctv-loading').classList.add('d-none');
    $id('ctv-content').classList.remove('d-none');
  }

  function load() {
    return window.axios.get('/api/v1/trips/' + id).then((response) => render(response.data.data));
  }

  load().catch((error) => {
    $id('ctv-loading').classList.add('d-none');
    $id('ctv-error').textContent = error.response.data.message;
    $id('ctv-error').classList.remove('d-none');
  });

  // Direct approval (FRD: "من شاشة تفاصيل الرحلة يمكن للعميل الاعتماد بشكل مباشر") — needs a signature, so
  // only with the account's saved one; without it the review page opens to sign there.
  $id('ctv-approve').addEventListener('click', function () {
    if (!window.clientTripHasSavedSignature) {
      window.location.assign(window.clientTripReviewUrl);
      return;
    }

    confirmBox({ title: t.approve_title, message: t.approve_message, note: t.approve_saved, okLabel: t.approve_title }).then((ok) => {
      if (!ok) return;

      const data = new FormData();
      data.append('use_saved_signature', '1');
      $id('ctv-error').classList.add('d-none');
      submitReview(id, 'approve', data)
        .then(() => window.location.assign(window.location.pathname + '?done=approved'))
        .catch((error) => {
          if (error.otpCancelled) return;
          $id('ctv-error').textContent = error.response.data.message;
          $id('ctv-error').classList.remove('d-none');
        });
    });
  });
});
