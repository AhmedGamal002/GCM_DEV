/**
 * GCM's trip details (FRD V01.14 §1.14 "تفاصيل رحلة رقم …", part 7.4) — GET /api/v1/trips/{id}, with the
 * history card (staff only: the API sends no `events` to anyone else), "remind the client" (no code) and
 * "cancel the trip" (confirmation + reason + the e-mailed code). Which buttons show is the resource's `actions`.
 *
 * Every value from the API is set as text — names, comments and the history's reasons are user input (the
 * client's comment too: an outside user's text shown to staff).
 */
'use strict';

$(function () {
  const cfg = window.tripViewConfig;
  const t = cfg.t;
  const $id = (x) => document.getElementById(x);
  const text = (selector, value) => {
    document.querySelectorAll(selector).forEach((n) => (n.textContent = value === null || value === undefined || value === '' ? '—' : value));
  };
  const v = (key, value) => text('[data-v="' + key + '"]', value);
  const hide = (key, on) => {
    const row = document.querySelector('[data-row="' + key + '"]');
    if (row) row.classList.toggle('d-none', on);
  };

  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };

  function showFlash(message) {
    $id('tv-flash').textContent = message;
    $id('tv-flash').classList.remove('d-none');
  }

  function showError(error) {
    const data = error && error.response && error.response.data;
    $id('tv-error').textContent = (data && data.message) || window.gcmErrorMessage(error);
    $id('tv-error').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  const person = (entity) => {
    if (!entity) return '';
    const who = entity.affiliation === 'gcm' ? t.gcm : entity.entity_name;

    return entity.name || entity.plate ? (entity.name || entity.plate) + (who ? ' — ' + who : '') : '';
  };

  const capacityText = (c) => (c ? c.name + ' · ' + c.capacity_ton + ' ' + t.ton + ' / ' + c.capacity_cbm + ' ' + t.cbm : '');

  function render(trip) {
    v('code', trip.code);
    const badge = $id('tv-status-badge');
    badge.className = 'badge ' + (statusClass[trip.status] || 'bg-label-secondary');
    badge.textContent = trip.status_label;

    v('date', trip.trip_at ? moment(trip.trip_at).format('YYYY-MM-DD HH:mm') : '');
    v('driver', person(trip.driver));
    v('vehicle', person(trip.vehicle));
    v('going', person(trip.going_asset));
    v('returning', person(trip.returning_asset));
    v('company', trip.company && trip.company.name);
    v('project', trip.project && trip.project.name);
    v('representative', trip.project && trip.project.representative ? trip.project.representative.name : '');

    // The project's map link is validated http/https on save; built as an element anyway.
    document.querySelectorAll('[data-v="location"]').forEach((n) => {
      n.textContent = '—';
      const url = trip.location_url || (trip.project && trip.project.location_url);
      if (url && /^https?:\/\//i.test(url)) {
        const a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = t.open_map;
        n.textContent = '';
        n.appendChild(a);
      }
    });

    v('po', trip.purchase_order ? trip.purchase_order.number : trip.linked_to_purchase_order ? '' : t.without_po);
    v('main_service', trip.main_service && trip.main_service.name);
    v('sub_service', trip.sub_service && trip.sub_service.name);
    v('capacity', capacityText(trip.capacity));
    const supplying = trip.main_service && trip.main_service.service_type === 'supplying';
    hide('quantity', supplying);
    v(
      'quantity',
      trip.quantity_ton === null && trip.quantity_cbm === null
        ? ''
        : [trip.quantity_ton !== null ? trip.quantity_ton + ' ' + t.ton : null, trip.quantity_cbm !== null ? trip.quantity_cbm + ' ' + t.cbm : null].filter(Boolean).join(' / ')
    );
    v('facility', trip.facility && trip.facility.name);
    v('trip_cost', trip.trip_cost === null || trip.trip_cost === undefined ? '' : trip.trip_cost);
    v('ancillary_cost', trip.ancillary_cost === null || trip.ancillary_cost === undefined ? '' : trip.ancillary_cost);
    v('client_approver', trip.client_approver && trip.client_approver.name);
    v('gcm_approver', trip.gcm_approver && trip.gcm_approver.name);
    v('updated', trip.updated_by ? trip.updated_by.name + (trip.updated_at ? ' — ' + moment(trip.updated_at).format('YYYY-MM-DD HH:mm') : '') : '');

    const files = trip.files || {};
    $id('tv-photo').innerHTML = '';
    if (files['live-photo']) {
      const img = document.createElement('img');
      img.src = files['live-photo'];
      img.alt = '';
      img.className = 'img-fluid rounded border';
      img.style.maxHeight = '320px';
      $id('tv-photo').appendChild(img);
    } else {
      $id('tv-photo').textContent = '—';
    }

    const numbers = { 'delivery-note': trip.delivery_note_number, 'waste-manifest': trip.waste_manifest_number, 'recycle-receipt': trip.recycle_receipt_number };
    document.querySelectorAll('[data-number]').forEach((n) => (n.textContent = numbers[n.dataset.number] || '—'));
    document.querySelectorAll('[data-file]').forEach((n) => {
      const url = files[n.dataset.file];
      n.innerHTML = url ? window.gcmViewAttachment(url) : '—';
    });

    $id('tv-client-comment').textContent = trip.client_comment || '—';

    // The editor's HTML was sanitised on the server (RichText); it is still only inserted into its own card.
    $id('tv-additional-card').classList.toggle('d-none', !trip.additional_data);
    $id('tv-additional').innerHTML = trip.additional_data || '';

    const a = trip.actions || {};
    const editable = cfg.canManage && (a.update || a.final_approve || a.accept_objection || a.reject_objection);
    $id('tv-edit').classList.toggle('d-none', !editable);
    $id('tv-edit').href = cfg.editUrl;
    $id('tv-remind').classList.toggle('d-none', !(cfg.canManage && a.remind_client));
    $id('tv-cancel').classList.toggle('d-none', !(cfg.canManage && a.cancel));

    if (trip.events) drawHistory(trip.events);
  }

  // ---------------------------------------------------------------- history (staff)

  function changesCell(event) {
    const cell = document.createElement('div');
    if (event.reason) {
      const r = document.createElement('div');
      r.textContent = event.reason; // the client's / staff's words: text
      cell.appendChild(r);
    }

    Object.entries(event.changes || {}).forEach(([field, value]) => {
      const line = document.createElement('div');
      line.className = 'small text-muted';
      if (value && typeof value === 'object' && ('from' in value || 'to' in value)) {
        line.textContent = field + ': ' + (value.from === null || value.from === '' ? t.empty : value.from) + ' → ' + (value.to === null || value.to === '' ? t.empty : value.to);
      } else {
        line.textContent = field + ': ' + JSON.stringify(value);
      }
      cell.appendChild(line);
    });

    return cell;
  }

  function drawHistory(events) {
    $id('tv-history-card').classList.remove('d-none');
    const table = $('#tv-history');
    if ($.fn.DataTable.isDataTable(table)) table.DataTable().destroy();

    table.DataTable({
      data: events.slice().reverse(),
      order: [[0, 'desc']],
      columns: [{ data: 'created_at' }, { data: 'type_label' }, { data: 'actor' }, { data: 'to_status' }, { data: 'id' }],
      columnDefs: [
        { targets: 0, render: (d, type, row) => (type === 'display' ? '<span dir="ltr">' + $('<div>').text(moment(row.created_at).format('YYYY-MM-DD HH:mm')).html() + '</span>' : row.created_at) },
        { targets: 1, render: (d, type, row) => $('<div>').text(row.type_label || row.type).html() },
        { targets: 2, render: (d, type, row) => $('<div>').text(row.actor ? row.actor.name : '—').html() },
        { targets: 3, render: (d, type, row) => $('<div>').text(row.to_status ? cfg.statuses[row.to_status] || row.to_status : '—').html() },
        { targets: 4, orderable: false, render: (d, type, row) => (type === 'display' ? changesCell(row).innerHTML : row.reason || '') }
      ],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: t.search,
        emptyTable: t.no_events,
        info: t.info,
        infoEmpty: t.info_empty,
        paginate: { next: '<i class="ti ti-chevron-right ti-sm"></i>', previous: '<i class="ti ti-chevron-left ti-sm"></i>' }
      },
      dom: '<"row mx-2 mt-2"<"col-md-6"l><"col-md-6"f>>t<"row mx-2 mb-2"<"col-md-6"i><"col-md-6"p>>'
    });
  }

  // ---------------------------------------------------------------- actions

  function load() {
    return window.axios
      .get(cfg.apiBase + '/' + cfg.id)
      .then((response) => render(response.data.data))
      .catch(showError);
  }

  $id('tv-remind').addEventListener('click', function () {
    $id('tv-error').classList.add('d-none');
    this.disabled = true;
    window.axios
      .post(cfg.apiBase + '/' + cfg.id + '/remind-client')
      .then((response) => {
        const n = (response.data.data && response.data.data.sent_to) || 0;
        showFlash(t.reminder_sent.replace(':count', n));
        return load();
      })
      .catch(showError)
      .finally(() => (this.disabled = false));
  });

  const cancelModalEl = $id('tv-cancel-modal');
  $id('tv-cancel').addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(cancelModalEl).show());

  $id('tv-cancel-ok').addEventListener('click', async function () {
    const modal = bootstrap.Modal.getOrCreateInstance(cancelModalEl);
    const reason = $id('tv-cancel-reason').value.trim();
    modal.hide();
    window.gcmBusy.start();

    try {
      await window.gcmWithOtp(() => window.axios.post(cfg.apiBase + '/' + cfg.id + '/cancel', reason ? { reason } : {}), { purpose: t.cancel_purpose });
      window.gcmBusy.stop();
      showFlash(t.cancel_done);
      await load();
    } catch (error) {
      window.gcmBusy.stop();
      if (!error || !error.otpCancelled) showError(error);
    }
  });

  load();
});
