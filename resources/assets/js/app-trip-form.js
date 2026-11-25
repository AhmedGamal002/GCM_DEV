/**
 * GCM's trip form — create and edit (FRD V01.14 §1.14, part 7.4). The lists are gcmTripFormLists' (the cascading
 * choices of 7.2), the location / photo / signature widgets are the 7.3 components, and the actions behind
 * `otp` (final approval, rejecting the client's objection) are wrapped in gcmWithOtp.
 *
 *  - add   POST /api/v1/trips (a trip that needs no client approval is created completed, with its signatures — د4/د5)
 *  - edit  PATCH /api/v1/trips/{id}; and, by the trip's status, the final approval, accepting / rejecting the
 *          client's objection (`actions` of the resource say which buttons apply)
 *
 * Whatever the form allows, the server re-checks every choice and answers 422 on the field's name.
 */
'use strict';

$(function () {
  const cfg = window.tripFormConfig;
  const t = cfg.t;
  const editing = cfg.mode === 'edit';
  const form = document.getElementById('tripForm');
  const el = (name) => document.getElementById('tf-' + name);
  const wrapper = (key) => form.querySelector('[data-field="' + key + '"]');
  const gi = (key, fallback) => (window.gcmI18n && window.gcmI18n[key]) || fallback;

  const LIST_FIELDS = [
    'driver_id', 'vehicle_id', 'company_id', 'project_id', 'purchase_order_id', 'main_service_id', 'sub_service_id',
    'going_asset_id', 'returning_asset_id', 'intermediate_facility_id', 'client_approver_id', 'gcm_approver_id'
  ];
  const selects = {};
  LIST_FIELDS.forEach((f) => (selects[f] = el(f)));

  const quill = new Quill('#tf-additional-data-editor', { theme: 'snow', placeholder: '' });

  let prefilling = false; // while an edit loads its values nothing is reset or defaulted
  const state = {
    trip: null, // the loaded trip (edit)
    capacity: null, // GET options/capacity → data
    converter: null,
    quantityTouched: false,
    poList: [],
    poMeta: null,
    mainService: null,
    subService: null,
    pad: null,
    pendingCompletion: null, // 'final-approve' | 'reject-objection' while the signatures card is open (edit)
    savedSignature: false
  };

  const radio = (name) => form.querySelector('input[name="' + name + '"]:checked');
  const withoutPo = () => !!radio('requires_purchase_order') && radio('requires_purchase_order').value === '0';
  /** The date-time field is the user's wall clock: sent WITH its offset, so the server reads the same instant whatever zone it runs in. */
  const isoAt = () => (el('trip_at').value ? window.moment(el('trip_at').value).format() : null);
  const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
  const fmt = (s, map) => Object.keys(map).reduce((out, k) => out.replace(':' + k, map[k]), s);

  // ---------------------------------------------------------------- errors

  /** A choice the lists cleared because it no longer fits: say so, under its field, until the user chooses again. */
  function notice(field) {
    const holder = wrapper(field);
    const message = field === 'sub_service_id' ? t.cleared_sub_service : field === 'main_service_id' ? t.cleared_main_service : null;
    if (!holder || !message) return;

    holder.querySelectorAll('.tf-note').forEach((n) => n.remove());
    const note = document.createElement('div');
    note.className = 'tf-note text-warning small mt-1';
    note.textContent = message;
    holder.appendChild(note);
  }

  function clearErrors() {
    el('error').classList.add('d-none');
    form.querySelectorAll('.tf-note').forEach((n) => n.remove());
    form.querySelectorAll('.tf-feedback').forEach((n) => n.remove());
    form.querySelectorAll('.is-invalid').forEach((n) => n.classList.remove('is-invalid'));
  }

  function fieldError(key, message) {
    const holder = wrapper(key);
    if (!holder) return false;

    const note = document.createElement('div');
    note.className = 'tf-feedback text-danger small mt-1';
    note.textContent = message; // text, never HTML
    holder.appendChild(note);

    return true;
  }

  function showError(error) {
    const data = error && error.response && error.response.data;
    const errors = data && data.errors;
    let shown = false;

    if (errors && typeof errors === 'object') {
      Object.keys(errors).forEach((key) => {
        const message = Array.isArray(errors[key]) ? errors[key][0] : String(errors[key]);
        if (fieldError(key, message)) shown = true;
      });
    }

    const box = el('error');
    box.textContent = shown ? gi('invalid', '') : (data && data.message) || window.gcmErrorMessage(error);
    box.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ---------------------------------------------------------------- the lists

  const lists = window.gcmTripFormLists({
    staff: true,
    tripId: cfg.tripId,
    selects,
    onChange: (field, item) => {
      if (item && wrapper(field)) wrapper(field).querySelectorAll('.tf-note').forEach((n) => n.remove());
      if (field === 'project_id' && !prefilling) {
        // another project: the "without PO" the previous one forced no longer applies
        el('rpo-yes').checked = true;
        el('po-locked').classList.add('d-none');
        lists.setExtra('requires_purchase_order', true);
      }
      if (field === 'vehicle_id') showVehicle(item);
      if (field === 'returning_asset_id') showReturning(item);
      if (field === 'main_service_id') mainServiceChosen(item);
      if (field === 'sub_service_id') subServiceChosen(item);
      if (field === 'purchase_order_id') updateCosts();
    },
    onCapacity: capacityLoaded,
    onPurchaseOrders: purchaseOrdersLoaded,
    onInvalidated: (field) => notice(field),
    onError: (field, error) => {
      el('error').textContent = (error && error.response && error.response.data && error.response.data.message) || window.gcmErrorMessage(error);
      el('error').classList.remove('d-none');
    }
  });

  function showVehicle(vehicle) {
    el('vehicle_type').textContent = (vehicle && vehicle.category && vehicle.category.name) || '—';
    const embedded = vehicle && vehicle.embedded_container && vehicle.embedded_container.capacity;
    el('vehicle_capacity').textContent = embedded ? embedded.name + ' · ' + embedded.capacity_ton + ' t / ' + embedded.capacity_cbm + ' m³' : '—';
  }

  function showReturning(asset) {
    const c = asset && asset.capacity;
    el('returning_capacity').textContent = c ? c.name + ' · ' + c.capacity_ton + ' t / ' + c.capacity_cbm + ' m³' : '—';
  }

  function mainServiceChosen(service) {
    state.mainService = service;
    const supplying = !!service && (service.is_supplying === true || service.service_type === 'supplying');
    ['quantity_ton', 'quantity_cbm'].forEach((k) => wrapper(k).classList.toggle('d-none', supplying));
    updateCosts();
  }

  function subServiceChosen(sub) {
    state.subService = sub;
    const requires = !!sub && sub.requires_recycle_receipt === true;
    const hasValues = !!el('recycle_receipt_number').value || !!(state.trip && state.trip.files && state.trip.files['recycle-receipt']);
    form.querySelectorAll('[data-recycle]').forEach((n) => n.classList.toggle('d-none', !requires && !hasValues));
    form.querySelector('[data-recycle-required]').classList.toggle('d-none', !requires);
  }

  // ---------------------------------------------------------------- capacity and quantity

  function capacityLoaded(data) {
    state.capacity = data && data.capacity ? data : null;
    if (data && data.converter) state.converter = data.converter;

    const hint = el('quantity-hint');
    if (!state.capacity) {
      hint.textContent = '';

      return;
    }

    const max = state.capacity.default_quantity;
    hint.textContent = fmt(t.max_capacity, { ton: max.ton, cbm: max.cbm });

    // A new trip starts at the container's full capacity (FRD); staff may lower it. Never overwrite what they typed.
    if (!editing && !state.quantityTouched) {
      el('quantity_ton').value = max.ton;
      el('quantity_cbm').value = max.cbm;
    }
  }

  function convert(from) {
    if (!state.converter) return;
    state.quantityTouched = true;
    const q = window.gcmTripQuantity;

    if (from === 'ton') el('quantity_cbm').value = el('quantity_ton').value === '' ? '' : q.toCbm(el('quantity_ton').value, state.converter);
    else el('quantity_ton').value = el('quantity_cbm').value === '' ? '' : q.toTon(el('quantity_cbm').value, state.converter);

    quantityCheck();
  }

  /** The quantity may be lowered but never raised above the container (FRD) — the server enforces it too. */
  function quantityCheck() {
    form.querySelectorAll('[data-field="quantity_ton"] .tf-feedback').forEach((n) => n.remove());
    const max = state.capacity && state.capacity.default_quantity;
    if (max && num(el('quantity_ton').value) !== null && Number(el('quantity_ton').value) > max.ton + 1e-9) {
      fieldError('quantity_ton', fmt(t.over_capacity, { ton: max.ton, cbm: max.cbm }));
    }
  }

  el('quantity_ton').addEventListener('input', () => convert('ton'));
  el('quantity_cbm').addEventListener('input', () => convert('cbm'));

  // ---------------------------------------------------------------- purchase orders and costs

  function purchaseOrdersLoaded(response) {
    state.poList = response.data || [];
    state.poMeta = response.meta || null;

    // FRD / ب6: when no purchase order covers the choice the trip goes without one (the server would refuse
    // "needs a PO" with nothing to charge). Switched for the user, who can still flip it back.
    const none = !!state.poMeta && state.poMeta.without_purchase_order_only === true && !prefilling;

    if (none && !withoutPo()) {
      el('rpo-no').checked = true;
      lists.setExtra('requires_purchase_order', false);
    }

    el('po-locked').classList.toggle('d-none', !none && !withoutPo());
    updateCosts();
  }

  function selectedPurchaseOrder() {
    const id = lists.get('purchase_order_id');

    return state.poList.find((po) => po.id === id) || null;
  }

  function updateCosts() {
    const manual = withoutPo();
    const trip = el('trip_cost');
    const ancillary = el('ancillary_cost');
    trip.readOnly = !manual;
    ancillary.readOnly = !manual;
    trip.required = manual;
    wrapper('trip_cost').classList.toggle('tf-required', manual);

    const po = selectedPurchaseOrder();
    const line = po && po.line;

    if (manual) {
      el('cost-hint').textContent = t.cost_manual;
      el('ancillary-wrap') && el('ancillary-wrap').classList.remove('d-none');

      return;
    }

    if (line) {
      trip.value = line.trip_cost;
      ancillary.value = line.ancillary_cost;
      el('cost-hint').textContent = t.cost_from_po;
      el('ancillary-wrap').classList.toggle('d-none', line.quota_method !== 'trips_and_ancillary');
      el('po-hint').textContent = fmt(t.po_line, { cost: line.trip_cost, status: line.status_label || '' });
    } else {
      if (!editing || po) {
        trip.value = '';
        ancillary.value = '';
      }
      el('cost-hint').textContent = po ? t.po_none_for_service : editing && state.trip ? '' : t.cost_none;
      el('po-hint').textContent = po && !line ? t.po_none_for_service : '';
    }
  }

  form.querySelectorAll('input[name="requires_purchase_order"]').forEach((r) =>
    r.addEventListener('change', () => {
      lists.setExtra('requires_purchase_order', !withoutPo());
      updateCosts();
    })
  );

  el('trip_at').addEventListener('change', () => lists.setExtra('trip_at', isoAt()));

  // ---------------------------------------------------------------- location (typed) and photo (uploaded)

  /**
   * The location the staff member typed (he is not at the site — the GPS is the driver's form): a web link as it is, or
   * "latitude, longitude" turned into the same Google Maps link the driver's GPS widget builds. {value} (null = empty) or {error}.
   * The server checks it again: any http(s) link (TripInput::assertLocation).
   */
  function normalizeLocation(raw) {
    const text = (raw || '').trim();
    if (text === '') return { value: null };

    const coords = text.match(/^(-?\d{1,3}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)$/);
    if (coords) {
      const link = window.gcmMapsLink(parseFloat(coords[1]), parseFloat(coords[2]));

      return link ? { value: link } : { error: t.location_invalid };
    }

    return /^https?:\/\//i.test(text) ? { value: text } : { error: t.location_invalid };
  }

  function showLocationLink() {
    const place = normalizeLocation(el('location_url').value);
    el('location-link').classList.toggle('d-none', !place.value);
    if (place.value) el('location-link').href = place.value;
  }

  el('location_url').addEventListener('input', showLocationLink);
  el('location_url').addEventListener('blur', () => {
    // coordinates become the link they stand for, so what is saved is what is shown
    const place = normalizeLocation(el('location_url').value);
    if (place.value) el('location_url').value = place.value;
    showLocationLink();
  });

  // ---------------------------------------------------------------- signatures

  const clientBlock = el('client-signature-block');

  function padReady() {
    if (!state.pad) state.pad = window.gcmSignaturePad(el('signature-pad'), { height: 180 });

    return state.pad;
  }

  function showCompletionCard(needsClient) {
    el('completion-card').classList.remove('d-none');
    clientBlock.classList.toggle('d-none', !needsClient);
    padReady();
    el('completion-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  el('sig-clear').addEventListener('click', () => padReady().clear());
  form.querySelectorAll('input[name="gcm_signature_source"]').forEach((r) =>
    r.addEventListener('change', () => {
      el('signature-pad').style.opacity = radio('gcm_signature_source').value === 'saved' ? '0.4' : '1';
    })
  );

  // The account's saved signature (if any) is offered first.
  window.axios
    .get('/api/v1/me')
    .then((response) => {
      const me = response.data.data || response.data;
      if (me && me.signature_url) {
        state.savedSignature = true;
        el('saved-signature-img').src = me.signature_url;
        el('saved-signature-wrap').classList.remove('d-none');
        el('sig-saved').checked = true;
        el('signature-pad').style.opacity = '0.4';
      }
    })
    .catch(() => {});

  if (!editing) {
    form.querySelectorAll('input[name="requires_client_approval"]').forEach((r) =>
      r.addEventListener('change', () => {
        if (radio('requires_client_approval').value === '0') showCompletionCard(true);
        else el('completion-card').classList.add('d-none');
      })
    );
  }

  /** Adds the completing step's signatures to `data`; returns an error message or null. */
  async function appendCompletion(data, needsClient) {
    if (needsClient) {
      const file = el('client_signature').files[0];
      if (!file) return { key: 'client_signature', message: t.upload_client_signature };
      data.append('client_signature', file);
      const stamp = el('client_stamp').files[0];
      if (stamp) data.append('client_stamp', stamp);
      if (el('client_signer_name').value) data.append('client_signer_name', el('client_signer_name').value);
    }

    if (radio('gcm_signature_source').value === 'saved' && state.savedSignature) {
      data.append('use_saved_gcm_signature', '1');
    } else {
      const pad = padReady();
      if (pad.isEmpty()) return { key: 'gcm_signature', message: t.sign_first };
      data.append('gcm_signature', await pad.toFile('signature.png'));
      if (el('save-signature').checked) data.append('save_gcm_signature', '1');
    }

    return null;
  }

  // ---------------------------------------------------------------- building the request

  /** The trip's data. On a create only what was filled is sent; on an edit an emptied optional field is sent empty (cleared). */
  function buildData() {
    const data = new FormData();
    const put = (key, value, optional) => {
      if (value !== null && value !== undefined && value !== '') data.append(key, value);
      else if (editing && optional) data.append(key, '');
    };

    put('trip_at', isoAt());
    put('company_id', lists.get('company_id'));
    LIST_FIELDS.filter((f) => f !== 'company_id').forEach((f) => {
      const optional = ['going_asset_id', 'returning_asset_id', 'intermediate_facility_id', 'sub_service_id', 'purchase_order_id', 'client_approver_id', 'gcm_approver_id'].includes(f);
      put(f, lists.get(f), optional);
    });
    data.append('requires_purchase_order', withoutPo() ? '0' : '1');
    if (!editing) data.append('requires_client_approval', radio('requires_client_approval').value);
    if (withoutPo()) {
      put('trip_cost', el('trip_cost').value);
      put('ancillary_cost', el('ancillary_cost').value, true);
    }

    put('quantity_ton', el('quantity_ton').value, true);
    put('quantity_cbm', el('quantity_cbm').value, true);
    put('delivery_note_number', el('delivery_note_number').value, true);
    put('waste_manifest_number', el('waste_manifest_number').value, true);
    put('recycle_receipt_number', el('recycle_receipt_number').value, true);
    put('location_url', normalizeLocation(el('location_url').value).value, true);
    data.append('additional_data', quill.getText().trim() === '' && !quill.root.querySelector('img') ? '' : quill.root.innerHTML);

    ['delivery_note', 'waste_manifest', 'recycle_receipt', 'other_attachment'].forEach((k) => {
      const file = el(k).files[0];
      if (file) data.append(k, file);
    });
    const photo = el('live_photo').files[0];
    if (photo) data.append('live_photo', photo);

    return data;
  }

  function guard() {
    const tooLarge = window.gcmFileGuard(form, cfg.limits);
    if (tooLarge) {
      el('error').textContent = tooLarge;
      el('error').classList.remove('d-none');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    return !tooLarge;
  }

  /** Sends one request; `otp` wraps it in the code box. The busy overlay stays up while the page changes. */
  async function send(kind, data, otp) {
    const url = {
      create: cfg.apiBase,
      update: cfg.apiBase + '/' + cfg.tripId,
      'final-approve': cfg.apiBase + '/' + cfg.tripId + '/final-approve',
      'accept-objection': cfg.apiBase + '/' + cfg.tripId + '/accept-objection',
      'reject-objection': cfg.apiBase + '/' + cfg.tripId + '/reject-objection'
    }[kind];
    if (kind === 'update') data.append('_method', 'PATCH');

    const request = () => window.axios.post(url, data, { headers: { 'Content-Type': 'multipart/form-data' }, onUploadProgress: window.gcmBusy.onUploadProgress });

    window.gcmBusy.start({ progress: true });

    try {
      const response = otp ? await window.gcmWithOtp(request, { purpose: otp }) : await request();
      const id = response.data.data.id;
      window.location.href = cfg.viewUrlBase + '/' + id; // the overlay stays until the page changes
    } catch (error) {
      window.gcmBusy.stop();
      if (error && error.otpCancelled) return;
      showError(error);
    }
  }

  async function submit(kind, needsCompletion, otp) {
    clearErrors();
    quantityCheck();

    const place = normalizeLocation(el('location_url').value);
    if (place.error) fieldError('location_url', place.error);

    if (form.querySelector('.tf-feedback') || !guard()) return;

    const data = buildData();

    if (needsCompletion) {
      const needsClient = !clientBlock.classList.contains('d-none');
      const problem = await appendCompletion(data, needsClient);
      if (problem) {
        fieldError(problem.key, problem.message);
        el('completion-card').scrollIntoView({ behavior: 'smooth', block: 'center' });

        return;
      }
    }

    await send(kind, data, otp);
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    if (!editing) {
      const completing = radio('requires_client_approval').value === '0';
      submit('create', completing, null);
    } else {
      submit('update', false, null);
    }
  });

  // ---------------------------------------------------------------- edit: load the trip, offer the right buttons

  const completionButton = el('confirm-complete');

  function openCompletion(kind) {
    state.pendingCompletion = kind;
    const needsClient = !state.trip || state.trip.status !== 'client_approved';
    showCompletionCard(needsClient);
    completionButton.querySelector('span').textContent = kind === 'final-approve' ? t.confirm_final : t.confirm_reject;
    completionButton.classList.remove('d-none');
    el('final-approve').classList.add('d-none');
    el('reject-objection').classList.add('d-none');
  }

  if (editing) {
    el('final-approve').addEventListener('click', () => openCompletion('final-approve'));
    el('reject-objection').addEventListener('click', () => openCompletion('reject-objection'));
    el('accept-objection').addEventListener('click', () => submit('accept-objection', false, null));
    completionButton.addEventListener('click', () => {
      const kind = state.pendingCompletion;
      submit(kind, true, kind === 'final-approve' ? t.final_purpose : t.reject_purpose);
    });
  }

  function existingFile(kind, url) {
    form.querySelectorAll('[data-existing="' + kind + '"]').forEach((n) => (n.innerHTML = url ? window.gcmViewAttachment(url) : ''));
  }

  function dateInput(iso) {
    return iso ? window.moment(iso).format('YYYY-MM-DDTHH:mm') : '';
  }

  function prefill(trip) {
    prefilling = true;
    state.trip = trip;
    if (trip.quantity_converter) state.converter = trip.quantity_converter;

    el('trip_at').value = dateInput(trip.trip_at);
    lists.setExtra('trip_at', isoAt());
    (trip.requires_purchase_order ? el('rpo-yes') : el('rpo-no')).checked = true;
    lists.setExtra('requires_purchase_order', !!trip.requires_purchase_order);

    const txt = {
      company_id: trip.company && trip.company.name,
      project_id: trip.project && trip.project.name,
      driver_id: trip.driver && trip.driver.name,
      vehicle_id: trip.vehicle && trip.vehicle.plate,
      going_asset_id: trip.going_asset && trip.going_asset.name,
      returning_asset_id: trip.returning_asset && trip.returning_asset.name,
      purchase_order_id: trip.purchase_order && trip.purchase_order.number,
      main_service_id: trip.main_service && trip.main_service.name,
      sub_service_id: trip.sub_service && trip.sub_service.name,
      intermediate_facility_id: trip.facility && trip.facility.name,
      client_approver_id: trip.client_approver && trip.client_approver.name,
      gcm_approver_id: trip.gcm_approver && trip.gcm_approver.name
    };
    const item = {
      company_id: trip.company,
      project_id: trip.project,
      driver_id: trip.driver,
      vehicle_id: trip.vehicle && Object.assign({}, trip.vehicle, { embedded_container: trip.vehicle.has_embedded_container && trip.capacity ? { capacity: trip.capacity } : null }),
      going_asset_id: trip.going_asset,
      returning_asset_id: trip.returning_asset,
      main_service_id: trip.main_service,
      sub_service_id: trip.sub_service
    };
    const own = {
      company_id: trip.company && trip.company.id,
      project_id: trip.project && trip.project.id,
      driver_id: trip.driver && trip.driver.id,
      vehicle_id: trip.vehicle && trip.vehicle.id,
      going_asset_id: trip.going_asset && trip.going_asset.id,
      returning_asset_id: trip.returning_asset && trip.returning_asset.id,
      purchase_order_id: trip.purchase_order && trip.purchase_order.id,
      main_service_id: trip.main_service && trip.main_service.id,
      sub_service_id: trip.sub_service && trip.sub_service.id,
      intermediate_facility_id: trip.facility && trip.facility.id,
      client_approver_id: trip.client_approver && trip.client_approver.id,
      gcm_approver_id: trip.gcm_approver && trip.gcm_approver.id
    };

    ['company_id', 'project_id', 'driver_id', 'vehicle_id', 'going_asset_id', 'returning_asset_id', 'purchase_order_id', 'main_service_id', 'sub_service_id', 'intermediate_facility_id', 'client_approver_id', 'gcm_approver_id'].forEach((f) => {
      if (own[f]) lists.set(f, { id: own[f], text: txt[f], item: item[f] || { id: own[f] } }, { silent: true });
    });

    el('quantity_ton').value = trip.quantity_ton === null ? '' : trip.quantity_ton;
    el('quantity_cbm').value = trip.quantity_cbm === null ? '' : trip.quantity_cbm;
    if (trip.trip_cost !== undefined) el('trip_cost').value = trip.trip_cost === null ? '' : trip.trip_cost;
    if (trip.ancillary_cost !== undefined) el('ancillary_cost').value = trip.ancillary_cost === null ? '' : trip.ancillary_cost;
    el('delivery_note_number').value = trip.delivery_note_number || '';
    el('waste_manifest_number').value = trip.waste_manifest_number || '';
    el('recycle_receipt_number').value = trip.recycle_receipt_number || '';
    el('location_url').value = trip.location_url || '';
    if (trip.location_url) {
      el('location-link').href = trip.location_url;
      el('location-link').classList.remove('d-none');
    }
    if (trip.additional_data) quill.clipboard.dangerouslyPasteHTML(trip.additional_data); // the server sanitised it (RichText)

    const files = trip.files || {};
    existingFile('delivery-note', files['delivery-note']);
    existingFile('waste-manifest', files['waste-manifest']);
    existingFile('recycle-receipt', files['recycle-receipt']);
    existingFile('other', files.other);
    el('photo-existing').innerHTML = files['live-photo'] ? window.gcmViewAttachment(files['live-photo']) : '';

    subServiceChosen(item.sub_service_id || null);
    updateCosts();
    // the lists' answers to the silent prefill arrive a moment later; they must not reset anything
    setTimeout(() => (prefilling = false), 1500);

    const actions = trip.actions || {};
    el('submit').classList.toggle('d-none', !actions.update);
    el('final-approve').classList.toggle('d-none', !actions.final_approve);
    el('accept-objection').classList.toggle('d-none', !actions.accept_objection);
    el('reject-objection').classList.toggle('d-none', !actions.reject_objection);
  }

  if (editing) {
    window.axios
      .get(cfg.apiBase + '/' + cfg.tripId)
      .then((response) => {
        prefill(response.data.data);
        el('loading').classList.add('d-none');
        el('form-holder').classList.remove('d-none');
      })
      .catch((error) => {
        el('loading').innerHTML = '<div class="card-body text-danger"></div>';
        el('loading').querySelector('.card-body').textContent = window.gcmErrorMessage(error);
      });
  } else {
    el('trip_at').value = window.moment().format('YYYY-MM-DDTHH:mm');
    lists.setExtra('trip_at', isoAt());
  }

  // Wrapped required-marks for fields the form makes mandatory only sometimes.
  form.querySelectorAll('[data-quantity]').forEach(() => {});
});
