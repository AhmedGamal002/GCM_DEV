/**
 * The driver's create-trip form (FRD V01.14 §1.14 "من منظور دور السائق", part 7.6): the minimum of data. Decision
 * ج12 — the trip can't be created without a LIVE location and a LIVE photo: the only ways to fill them are the
 * GPS and camera widgets (gcmLocate / gcmCamera — no gallery, no typed link), and the button stays off until
 * both are there. If the browser or the device refuses either, the widget's message says why and the form stays
 * unsent. The server requires both again and accepts only the widget's link format.
 *
 * Connection lost (decision أ7): a clear "try again" message, nothing saved on the phone — the form keeps what the
 * driver chose, he presses Create again.
 */
'use strict';

$(function () {
  const cfg = window.driverTripAddConfig;
  const t = cfg.t;
  const form = document.getElementById('driverTripForm');
  const el = (name) => document.getElementById('df-' + name);
  const wrapper = (key) => form.querySelector('[data-field="' + key + '"]');

  const FIELDS = ['company_id', 'project_id', 'vehicle_id', 'main_service_id', 'sub_service_id', 'going_asset_id', 'returning_asset_id'];
  const selects = {};
  FIELDS.forEach((f) => (selects[f] = el(f)));

  const state = { location: null, photo: null };

  // ---------------------------------------------------------------- errors

  function clearErrors() {
    el('error').classList.add('d-none');
    form.querySelectorAll('.df-feedback').forEach((n) => n.remove());
  }

  function fieldError(key, message) {
    const holder = wrapper(key);
    if (!holder) return false;
    const note = document.createElement('div');
    note.className = 'df-feedback text-danger small mt-1';
    note.textContent = message; // text, never HTML
    holder.appendChild(note);

    return true;
  }

  function showError(error) {
    const data = error && error.response && error.response.data;
    let shown = false;

    if (data && data.errors) {
      Object.keys(data.errors).forEach((key) => {
        if (fieldError(key, Array.isArray(data.errors[key]) ? data.errors[key][0] : String(data.errors[key]))) shown = true;
      });
    }

    el('error').textContent = shown ? (window.gcmI18n || {}).invalid || '' : (data && data.message) || window.gcmErrorMessage(error);
    el('error').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ---------------------------------------------------------------- the lists (the driver's own: no driver, no PO)

  window.gcmTripFormLists({
    staff: false,
    selects,
    onChange: (field, item) => {
      if (item && wrapper(field)) wrapper(field).querySelectorAll('.df-note').forEach((n) => n.remove());
      if (field === 'vehicle_id') el('vehicle_type').textContent = (item && item.category && item.category.name) || '—';
    },
    // a choice cleared because it no longer fits says so, under its field, until the driver chooses again
    onInvalidated: (field) => {
      const holder = wrapper(field);
      if (!holder || field !== 'sub_service_id') return;
      holder.querySelectorAll('.df-note').forEach((n) => n.remove());
      const note = document.createElement('div');
      note.className = 'df-note text-warning small mt-1';
      note.textContent = t.cleared_sub_service;
      holder.appendChild(note);
    },
    onError: (field, error) => {
      el('error').textContent = (error && error.response && error.response.data && error.response.data.message) || window.gcmErrorMessage(error);
      el('error').classList.remove('d-none');
    }
  });

  // ---------------------------------------------------------------- location and photo (both required, both live)

  function refreshSubmit() {
    const ready = !!state.location && !!state.photo;
    el('submit').disabled = !ready;
    el('hint').textContent = ready ? '' : t.choose_both;
  }

  async function takeLocation() {
    const button = el('get-location');
    button.disabled = true;
    el('location-status').className = 'small text-muted';
    el('location-status').textContent = t.locating;
    wrapper('location_url').querySelectorAll('.df-feedback').forEach((n) => n.remove());

    try {
      const here = await window.gcmLocate();
      state.location = here;
      el('location-status').className = 'small text-success';
      el('location-status').textContent = t.location_ok;
      el('location-link').href = here.mapsUrl;
      el('location-link').classList.remove('d-none');
    } catch (error) {
      state.location = null;
      el('location-status').textContent = '';
      el('location-link').classList.add('d-none');
      fieldError('location_url', error.message);
    } finally {
      button.disabled = false;
      refreshSubmit();
    }
  }

  el('get-location').addEventListener('click', takeLocation);

  el('take-photo').addEventListener('click', async function () {
    this.disabled = true;
    wrapper('live_photo').querySelectorAll('.df-feedback').forEach((n) => n.remove());

    try {
      // The stamp carries the location (and the page asks for it first if it doesn't have it yet).
      if (!state.location) await takeLocation();
      const photo = await window.gcmCamera.capture({ location: state.location });
      state.photo = photo.file;
      el('photo-preview').src = URL.createObjectURL(photo.blob);
      el('photo-preview').classList.remove('d-none');
      el('photo-status').className = 'small text-success';
      el('photo-status').textContent = t.photo_ok;
    } catch (error) {
      if (!error || error.code !== 'cancelled') fieldError('live_photo', error.message);
    } finally {
      this.disabled = false;
      refreshSubmit();
    }
  });

  refreshSubmit();
  // Ask for the location as the page opens (the browser's permission box); the button is there if it is refused.
  takeLocation();

  // ---------------------------------------------------------------- send

  const escapeHtml = (v) => $('<div>').text(v).html();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();

    if (!state.location) return fieldError('location_url', t.need_location);
    if (!state.photo) return fieldError('live_photo', t.need_photo);

    const tooLarge = window.gcmFileGuard(form, cfg.limits);
    if (tooLarge) {
      el('error').textContent = tooLarge;
      el('error').classList.remove('d-none');

      return;
    }

    const data = new FormData();
    FIELDS.forEach((f) => {
      const value = selects[f].value;
      if (value) data.append(f, value);
    });
    data.append('location_url', state.location.mapsUrl);
    data.append('live_photo', state.photo);
    const other = el('other_attachment').files[0];
    if (other) data.append('other_attachment', other);
    const extra = el('additional_data').value.trim();
    if (extra) data.append('additional_data', escapeHtml(extra).replace(/\n/g, '<br>'));

    window.gcmBusy.start({ progress: true });

    try {
      const response = await window.axios.post(cfg.apiBase, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      });
      window.location.href = cfg.viewUrlBase + '/' + response.data.data.id; // the overlay stays until the page changes
    } catch (error) {
      window.gcmBusy.stop();
      showError(error); // no connection: gcmErrorMessage says so, and the form keeps everything — press Create again
    }
  });
});
