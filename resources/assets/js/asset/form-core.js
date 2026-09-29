/**
 * Shared engine for the create & edit asset forms (both render the
 * `tenant.assets._form` Blade partial). Handles: reference dropdowns
 * (capacity categories filtered by the chosen asset type, compatible
 * vehicle-category checkboxes), the Quill editor, inline per-field
 * validation (client-side + mapping of the server's 422 errors), and the
 * JSON submit.
 *
 * On edit the FRD allows ONLY the name to change — every other field is
 * rendered pre-filled but disabled.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initAssetForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const PICK_ONE_MSG = 'Select at least one option.';

export function initAssetForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.assetFormTranslations || {};

  const form = document.getElementById('assetForm');
  if (!form) return;

  const errorBox = document.getElementById('asset-form-error');
  const loading = document.getElementById('asset-form-loading');
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

  const clearOnInteract = (e) => {
    if (e.target.matches('input, select, textarea')) clearFieldError(e.target);
  };
  form.addEventListener('input', clearOnInteract);
  form.addEventListener('change', clearOnInteract);

  // ---------------------------------------------------------- reference data
  const catBox = document.getElementById('compatible-vehicle-categories');
  const capSelect = document.getElementById('asset_capacity_category_id');
  const capPlaceholder = capSelect.options[0] ? capSelect.options[0].text : 'Select...';
  let allCapacities = [];

  function renderCapacityOptions(forType) {
    const chosen = capSelect.value;
    capSelect.innerHTML = '';
    capSelect.add(new Option(capPlaceholder, ''));
    allCapacities
      .filter((c) => !forType || c.applies_to === 'both' || c.applies_to === forType)
      .forEach((c) => {
        const opt = new Option(`${c.name} (${c.capacity_cbm} CBM / ${c.capacity_ton} TON)`, c.id);
        capSelect.add(opt);
      });
    if (chosen && capSelect.querySelector(`option[value="${chosen}"]`)) capSelect.value = chosen;

    // The <option>s were just rebuilt — Select2 snapshots them at init time,
    // so it needs a fresh wiring, not just a refresh (see gcm-select2.js).
    window.refreshGcmSelect(capSelect);
  }

  const refsReady = Promise.all([
    window.axios.get('/api/v1/vehicle-categories'),
    window.axios.get('/api/v1/asset-capacity-categories')
  ]).then(([cats, caps]) => {
    cats.data.data.forEach((c) => {
      const col = document.createElement('div');
      col.className = 'col';
      col.innerHTML =
        '<div class="form-check">' +
        '<input class="form-check-input" type="checkbox" name="compatible_vehicle_category_ids[]" value="' +
        c.id + '" id="cvc-' + c.id + '">' +
        '<label class="form-check-label" for="cvc-' + c.id + '">' + $('<div>').text(c.name).html() + '</label>' +
        '</div>';
      catBox.appendChild(col);
    });
    allCapacities = caps.data.data;
    const currentType = form.querySelector('input[name="asset_type"]:checked');
    renderCapacityOptions(currentType ? currentType.value : null);
  });

  form.querySelectorAll('input[name="asset_type"]').forEach((r) =>
    r.addEventListener('change', () => renderCapacityOptions(r.value))
  );

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
      if (el.disabled || el.closest('.d-none')) return;

      if (el.type === 'radio') {
        if (seenRadioGroups.has(el.name)) return;
        seenRadioGroups.add(el.name);
        if (!form.querySelector(`input[name="${el.name}"]:checked`)) fail(el, t.required || REQUIRED_MSG);
        return;
      }
      if (!el.value || (el.tagName === 'SELECT' && el.value === '')) fail(el, t.required || REQUIRED_MSG);
    });

    // At least one compatible vehicle category (create only — locked on edit).
    if (mode === 'create') {
      const anyChecked = form.querySelector('input[name="compatible_vehicle_category_ids[]"]:checked');
      if (!anyChecked) {
        const fb = form.querySelector('[data-feedback-for="compatible_vehicle_category_ids"]');
        if (fb) {
          fb.textContent = t.pick_one || PICK_ONE_MSG;
          fb.classList.remove('d-none');
        }
        if (!firstInvalid) firstInvalid = catBox;
      }
    }

    if (firstInvalid && firstInvalid.scrollIntoView) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (firstInvalid.focus) firstInvalid.focus({ preventScroll: true });
    }
    return !firstInvalid;
  }

  // -------------------------------------------------------- server errors
  function applyServerErrors(errors) {
    let firstInvalid = null;
    const unmapped = [];
    Object.entries(errors).forEach(([key, messages]) => {
      const base = key.split('.')[0];
      let el = form.querySelector(`[name="${base}"]`) || form.querySelector(`[name="${base}[]"]`);
      if (base === 'compatible_vehicle_category_ids') {
        const fb = form.querySelector('[data-feedback-for="compatible_vehicle_category_ids"]');
        if (fb) {
          fb.textContent = messages[0];
          fb.classList.remove('d-none');
        }
        el = catBox;
      } else if (el) {
        showFieldError(el, messages[0]);
      }
      if (el && !firstInvalid) firstInvalid = el;
      if (!el) unmapped.push(messages[0]);
    });
    if (unmapped.length) {
      errorBox.textContent = unmapped.join(' ');
      errorBox.classList.remove('d-none');
    }
    if (firstInvalid && firstInvalid.scrollIntoView) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else window.scrollTo(0, 0);
  }

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    let url = '/api/v1/assets';
    let method = 'post';
    let payload;

    if (mode === 'edit') {
      url = `/api/v1/assets/${id}`;
      method = 'patch';
      payload = { name: form.querySelector('#name').value };
    } else {
      payload = {
        name: form.querySelector('#name').value,
        asset_type: (form.querySelector('input[name="asset_type"]:checked') || {}).value,
        asset_capacity_category_id: capSelect.value,
        compatible_vehicle_category_ids: Array.from(
          form.querySelectorAll('input[name="compatible_vehicle_category_ids[]"]:checked')
        ).map((c) => c.value),
        operational_status: (form.querySelector('input[name="operational_status"]:checked') || {}).value,
        affiliation: (form.querySelector('input[name="affiliation"]:checked') || {}).value,
        purchase_date: form.querySelector('#purchase_date').value || null,
        additional_data: quill.getLength() > 1 ? quill.root.innerHTML : null
      };
    }

    window.axios[method](url, payload, { onUploadProgress: window.gcmBusy.onUploadProgress })
      .then(() => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/asset/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/asset/list'}?created=1`;
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
  function lockEverythingButName() {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (el.id !== 'name') el.disabled = true;
    });
    quill.disable();

    // Select2 only reads the disabled state when it's (re-)wired — a plain
    // .disabled = true on an already-wired select leaves its widget
    // looking clickable, so re-wire it now that it's disabled.
    window.refreshGcmSelect(capSelect);
  }

  function prefill(a) {
    form.querySelector('#name').value = a.name;
    const typeRadio = form.querySelector(`input[name="asset_type"][value="${a.asset_type}"]`);
    if (typeRadio) typeRadio.checked = true;
    renderCapacityOptions(a.asset_type);
    if (a.capacity_category) {
      capSelect.value = a.capacity_category.id;
      // Select2 is already wired (renderCapacityOptions just did it) — a
      // plain .value= doesn't refresh its display, only a 'change' event does.
      $(capSelect).trigger('change');
    }
    (a.compatible_vehicle_categories || []).forEach((c) => {
      const cb = form.querySelector(`input[name="compatible_vehicle_category_ids[]"][value="${c.id}"]`);
      if (cb) cb.checked = true;
    });
    const affRadio = form.querySelector(`input[name="affiliation"][value="${a.affiliation}"]`);
    if (affRadio) affRadio.checked = true;
    if (a.purchase_date) form.querySelector('#purchase_date').value = a.purchase_date;
    quill.root.innerHTML = a.additional_data || '';

    const stamp = document.getElementById('asset-last-updated');
    if (stamp && a.updated_by_name && t.last_updated_by) {
      const at = a.updated_at ? new Date(a.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', a.updated_by_name).replace(':at', at);
    }
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('asset-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/asset/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    Promise.all([refsReady, window.axios.get(`/api/v1/assets/${id}`)])
      .then(([, res]) => {
        prefill(res.data.data);
        lockEverythingButName();
        loading.classList.add('d-none');
        form.classList.remove('d-none');
      })
      .catch((error) => {
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }
}
