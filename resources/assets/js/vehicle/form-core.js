/**
 * Shared engine for the create & edit vehicle forms (both render the
 * `_vehicle-form` Blade partial). Handles: reference dropdowns, the Quill
 * editor, the embedded-container toggle, repeatable entry-permit rows,
 * digits-only enforcement on numeric fields, inline per-field validation
 * (client-side required/format checks + mapping of the server's 422
 * errors onto the matching field), and the multipart submit.
 *
 * Not picked up as its own Vite entry — it lives in a subdirectory, so
 * the `resources/assets/js/*.js` glob doesn't match it; the two thin
 * entry files import `initVehicleForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const NUMERIC_MSG = 'Only digits are allowed.';

export function initVehicleForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.vehicleFormTranslations || {};

  const form = document.getElementById('vehicleForm');
  if (!form) return;

  const errorBox = document.getElementById('vehicle-form-error');
  const loading = document.getElementById('vehicle-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  // ---------------------------------------------------------------- errors
  function feedbackEl(el) {
    const wrapper = el.closest('[class*="col-"]') || el.parentElement;
    return wrapper ? wrapper.querySelector('[data-feedback]') : null;
  }

  function showFieldError(el, msg) {
    if (el.type === 'radio' && el.name) {
      form.querySelectorAll(`input[name="${el.name}"]`).forEach((r) => r.classList.add('is-invalid'));
    } else {
      el.classList.add('is-invalid');
    }
    const fb = feedbackEl(el);
    if (fb) {
      fb.textContent = msg;
      fb.classList.remove('d-none');
    }
  }

  function clearFieldError(el) {
    if (el.type === 'radio' && el.name) {
      form.querySelectorAll(`input[name="${el.name}"]`).forEach((r) => r.classList.remove('is-invalid'));
    } else {
      el.classList.remove('is-invalid');
    }
    const fb = feedbackEl(el);
    if (fb) {
      fb.textContent = '';
      fb.classList.add('d-none');
    }
  }

  function clearAllErrors() {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('[data-feedback]').forEach((fb) => {
      fb.textContent = '';
      fb.classList.add('d-none');
    });
    errorBox.textContent = '';
    errorBox.classList.add('d-none');
  }

  // A field's error clears as soon as the user touches it.
  const clearOnInteract = (e) => {
    if (e.target.matches('input, select, textarea')) clearFieldError(e.target);
  };
  form.addEventListener('input', clearOnInteract);
  form.addEventListener('change', clearOnInteract);

  // -------------------------------------------------------------- numeric
  form.addEventListener('input', (e) => {
    if (e.target.matches('[data-numeric]')) {
      const cleaned = e.target.value.replace(/\D+/g, '');
      if (cleaned !== e.target.value) e.target.value = cleaned;
    }
  });

  // ----------------------------------------------------- client validation
  function validate() {
    clearAllErrors();

    let firstInvalid = null;
    const fail = (el, msg) => {
      showFieldError(el, msg);
      if (!firstInvalid) firstInvalid = el;
    };
    const seenRadioGroups = new Set();

    form.querySelectorAll('input[required], select[required], textarea[required]').forEach((el) => {
      if (el.closest('.d-none')) return; // hidden section (e.g. embedded fields when "no")
      if (el.type === 'file') return; // attachments are optional

      if (el.type === 'radio') {
        if (seenRadioGroups.has(el.name)) return;
        seenRadioGroups.add(el.name);
        if (!form.querySelector(`input[name="${el.name}"]:checked`)) {
          fail(el, t.required || REQUIRED_MSG);
        }
        return;
      }

      if (!el.value || (el.tagName === 'SELECT' && el.value === '')) {
        // Empty embedded-capacity when the compatible list came back empty
        // gets the specific "nothing compatible" message, not "required".
        if (el === capSelect && capSelect.options.length <= 1) {
          const { type } = embeddedInputs();
          const typeLabel = type === 'tank' ? t.tank || 'tank' : t.container || 'container';
          fail(el, (t.no_compatible_capacity || 'There is no compatible :type capacity recorded for the selected vehicle category.').replace(':type', typeLabel));
          return;
        }
        fail(el, t.required || REQUIRED_MSG);
        return;
      }

      if (el.hasAttribute('data-numeric') && !/^\d+$/.test(el.value)) {
        fail(el, t.numeric || NUMERIC_MSG);
      }
    });

    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus({ preventScroll: true });
    }
    return !firstInvalid;
  }

  // -------------------------------------------------------- server errors
  function keyToName(key) {
    const parts = key.split('.');
    return parts[0] + parts.slice(1).map((p) => `[${p}]`).join('');
  }

  function applyServerErrors(errors) {
    let firstInvalid = null;
    const unmapped = [];

    Object.entries(errors).forEach(([key, messages]) => {
      const el = form.querySelector(`[name="${keyToName(key)}"]`);
      if (el) {
        showFieldError(el, messages[0]);
        if (!firstInvalid) firstInvalid = el;
      } else {
        unmapped.push(messages[0]);
      }
    });

    if (unmapped.length) {
      errorBox.textContent = unmapped.join(' ');
      errorBox.classList.remove('d-none');
    }
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      window.scrollTo(0, 0);
    }
  }

  // ---------------------------------------------------------- reference data
  const catSelect = document.getElementById('vehicle_category_id');
  const capSelect = document.getElementById('embedded_asset_capacity_category_id');
  const capHint = document.getElementById('embedded-capacity-hint');

  const refsReady = window.axios.get('/api/v1/vehicle-categories').then((cats) => {
    cats.data.data.forEach((c) => catSelect.add(new Option(c.name, c.id)));
  });

  // ------------------------------------------------------- embedded toggle
  const typeWrapper = document.getElementById('embedded-type-wrapper');
  const capacityWrapper = document.getElementById('embedded-capacity-wrapper');

  // The embedded-capacity list is NOT the full set of capacity categories:
  // per FRD §1.5.3 it's only the ones the fleet can actually field for the
  // chosen (vehicle category + container/tank kind), derived through the
  // asset pool. Empty result => an error, not a fallback to "all".
  function embeddedInputs() {
    const has = form.querySelector('input[name="has_embedded_container"]:checked').value === '1';
    const typeEl = form.querySelector('input[name="embedded_container_type"]:checked');
    return { has, type: typeEl ? typeEl.value : null, vehicleCategoryId: catSelect.value || null };
  }

  let capReqSeq = 0;

  async function refreshEmbeddedCapacities(preserveValue) {
    const seq = ++capReqSeq;
    const keep = preserveValue != null ? preserveValue : capSelect.value;
    const { has, type, vehicleCategoryId } = embeddedInputs();

    clearFieldError(capSelect);
    capSelect.innerHTML = '';
    capSelect.add(new Option(t.select || 'Select...', ''));

    if (!has || !type || !vehicleCategoryId) {
      capHint.textContent = t.pick_category_and_type || 'Choose the vehicle category and container type first.';
      capHint.classList.remove('d-none');
      return;
    }

    try {
      const res = await window.axios.get('/api/v1/asset-capacity-categories', {
        params: { vehicle_category_id: vehicleCategoryId, asset_type: type }
      });
      // A newer change already fired — drop this stale response.
      if (seq !== capReqSeq) return;
      const caps = res.data.data;
      caps.forEach((c) =>
        capSelect.add(new Option(`${c.name} (${c.capacity_cbm} CBM / ${c.capacity_ton} TON)`, c.id))
      );

      if (caps.length === 0) {
        capHint.classList.add('d-none');
        const typeLabel = type === 'tank' ? t.tank || 'tank' : t.container || 'container';
        showFieldError(capSelect, (t.no_compatible_capacity || 'There is no compatible :type capacity recorded for the selected vehicle category.').replace(':type', typeLabel));
      } else {
        capHint.classList.add('d-none');
        if (keep && capSelect.querySelector(`option[value="${keep}"]`)) capSelect.value = keep;
      }
    } catch (e) {
      capHint.classList.add('d-none');
    }
  }

  function toggleEmbedded() {
    const has = form.querySelector('input[name="has_embedded_container"]:checked').value === '1';
    typeWrapper.classList.toggle('d-none', !has);
    capacityWrapper.classList.toggle('d-none', !has);
    if (!has) {
      form.querySelectorAll('input[name="embedded_container_type"]').forEach((r) => {
        r.checked = false;
        clearFieldError(r);
      });
      capSelect.value = '';
      clearFieldError(capSelect);
    } else {
      refreshEmbeddedCapacities();
    }
  }
  form.querySelectorAll('input[name="has_embedded_container"]').forEach((r) => r.addEventListener('change', toggleEmbedded));
  form.querySelectorAll('input[name="embedded_container_type"]').forEach((r) =>
    r.addEventListener('change', () => refreshEmbeddedCapacities())
  );
  catSelect.addEventListener('change', () => {
    if (embeddedInputs().has) refreshEmbeddedCapacities();
  });

  // --------------------------------------------------------- entry permits
  const container = document.getElementById('entry-permits-container');
  const template = document.getElementById('entry-permit-template');
  let permitIndex = 0;
  // API doc field <- form field name for an existing entry permit.
  const fromDoc = { id: 'id', area_name: 'area_name', permit_number: 'document_number', valid_to: 'valid_to' };

  function addPermitRow(permit) {
    const node = template.content.firstElementChild.cloneNode(true);
    const i = permitIndex++;
    node.querySelectorAll('[data-field]').forEach((input) => {
      const field = input.dataset.field;
      input.name = `entry_permits[${i}][${field}]`;
      if (permit && field !== 'attachment' && permit[fromDoc[field]] != null) {
        input.value = permit[fromDoc[field]];
      }
    });

    // Existing permit that already has an attachment — kept unless the
    // user picks a new file. Show a link to the current one (same bare
    // style as the single-document hints).
    if (permit && permit.has_attachment) {
      const hint = node.querySelector('[data-permit-current]');
      if (hint) {
        hint.innerHTML = '<a href="' + permit.download_url + '" target="_blank"><i class="ti ti-download"></i></a>';
        hint.classList.remove('d-none');
      }
    }

    node.querySelector('.remove-entry-permit').addEventListener('click', () => node.remove());
    container.appendChild(node);
  }
  document.getElementById('add-entry-permit').addEventListener('click', () => addPermitRow(null));

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    // Server limit: 4 MB per photo/document — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { default: 4096 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      window.scrollTo(0, 0);
      return;
    }

    const data = new FormData(form);
    data.set('additional_data', quill.root.innerHTML);
    for (const [key, value] of Array.from(data.entries())) {
      if (value instanceof File && value.size === 0 && value.name === '') data.delete(key);
    }

    let url = '/api/v1/vehicles';
    if (mode === 'edit') {
      url = `/api/v1/vehicles/${id}`;
      data.set('_method', 'PATCH');
    }

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    window.axios
      .post(url, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      })
      .then(() => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/vehicle/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/vehicle/list'}?created=1`;
        }
      })
      .catch((error) => {
        window.gcmBusy.stop();
        submitBtn.disabled = false;
        const res = error.response;
        if (res && res.status === 422 && res.data && res.data.errors) {
          applyServerErrors(res.data.errors);
        } else {
          errorBox.textContent = (res && res.data && res.data.message) || t.generic_error || 'Something went wrong.';
          errorBox.classList.remove('d-none');
          window.scrollTo(0, 0);
        }
      });
  });

  // --------------------------------------------------------- mode bootstrap
  function showCurrentFile(elId, url) {
    const el = document.getElementById(elId);
    el.innerHTML = `<a href="${url}" target="_blank">${url.split('/').pop()}</a>`;
    el.classList.remove('d-none');
  }

  async function prefill(v) {
    form.querySelector('#plate_letters').value = v.plate_letters;
    form.querySelector('#plate_numbers').value = v.plate_numbers;
    catSelect.value = v.category.id;

    form.querySelector(`input[name="has_embedded_container"][value="${v.has_embedded_container ? '1' : '0'}"]`).checked = true;
    if (v.embedded_container_type) {
      form.querySelector(`input[name="embedded_container_type"][value="${v.embedded_container_type}"]`).checked = true;
    }
    typeWrapper.classList.toggle('d-none', !v.has_embedded_container);
    capacityWrapper.classList.toggle('d-none', !v.has_embedded_container);
    if (v.has_embedded_container) {
      await refreshEmbeddedCapacities(v.embedded_capacity_category ? String(v.embedded_capacity_category.id) : null);
    }

    quill.root.innerHTML = v.additional_data || '';

    if (v.updated_by_name && t.last_updated_by) {
      const at = v.updated_at ? new Date(v.updated_at).toLocaleString() : '';
      document.getElementById('ve-updated-by').textContent = t.last_updated_by
        .replace(':name', v.updated_by_name)
        .replace(':at', at);
    }

    if (v.photo_front_url) showCurrentFile('photo_front_current', v.photo_front_url);
    if (v.photo_back_url) showCurrentFile('photo_back_current', v.photo_back_url);

    (v.documents || []).forEach((d) => {
      if (d.type === 'entry_permit') {
        addPermitRow(d);
        return;
      }
      const num = form.querySelector(`[name="documents[${d.type}][number]"]`);
      const val = form.querySelector(`[name="documents[${d.type}][valid_to]"]`);
      if (num) num.value = d.document_number;
      if (val) val.value = d.valid_to;
      if (d.has_attachment) {
        const hint = form.querySelector(`[data-doc-current="${d.type}"]`);
        if (hint) {
          hint.innerHTML = `<a href="${d.download_url}" target="_blank"><i class="ti ti-download"></i></a>`;
          hint.classList.remove('d-none');
        }
      }
    });
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('vehicle-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/vehicle/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    Promise.all([refsReady, window.axios.get(`/api/v1/vehicles/${id}`)])
      .then(([, res]) => prefill(res.data.data))
      .then(() => {
        loading.classList.add('d-none');
        form.classList.remove('d-none');
      })
      .catch((error) => {
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  } else {
    toggleEmbedded();
  }
}
