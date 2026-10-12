/**
 * Shared engine for the create & edit vehicle-category forms (both render
 * `tenant.vehicle-categories._form`): the name in English and in Arabic
 * (required) plus the embedded-container defaults the create-vehicle form
 * pre-fills for this category (has one or not, container/tank, capacity).
 * Lives in a subdirectory so the `resources/assets/js/*.js`
 * Vite glob doesn't pick it up; the thin entry files import
 * `initVehicleCategoryForm`.
 */
'use strict';

export function initVehicleCategoryForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.vehicleCategoryFormTranslations || {};

  const form = document.getElementById('vehicleCategoryForm');
  if (!form) return;

  const errorBox = document.getElementById('vehicle-category-form-error');
  const loading = document.getElementById('vehicle-category-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');

  window.initGcmSelects(form);

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
    if (e.target.matches('input, select')) {
      if (e.target.type === 'radio' && e.target.name) {
        form.querySelectorAll(`input[name="${e.target.name}"]`).forEach((r) => r.classList.remove('is-invalid'));
      } else {
        e.target.classList.remove('is-invalid');
      }
      const fb = feedbackEl(e.target);
      if (fb) {
        fb.textContent = '';
        fb.classList.add('d-none');
      }
    }
  };
  form.addEventListener('input', clearOnInteract);
  form.addEventListener('change', clearOnInteract);

  // ------------------------------------------------ embedded-container defaults
  const typeWrapper = document.getElementById('default-embedded-type-wrapper');
  const capacityWrapper = document.getElementById('default-embedded-capacity-wrapper');
  const capSelect = document.getElementById('default_embedded_asset_capacity_category_id');
  const capHint = document.getElementById('default-embedded-capacity-hint');

  function embeddedInputs() {
    const has = form.querySelector('input[name="default_has_embedded_container"]:checked').value === '1';
    const typeEl = form.querySelector('input[name="default_embedded_container_type"]:checked');
    return { has, type: typeEl ? typeEl.value : null };
  }

  // Same list the vehicle form offers: the asset capacities that apply to
  // the chosen kind (a capacity that applies to both fits either).
  let capReqSeq = 0;

  async function refreshCapacities(keep) {
    const seq = ++capReqSeq;
    const keepValue = keep != null ? String(keep) : capSelect.value;
    const { has, type } = embeddedInputs();

    capSelect.innerHTML = '';
    capSelect.add(new Option(t.select || 'Select...', ''));

    if (!has || !type) {
      capHint.textContent = t.pick_type || 'Choose the container type first.';
      capHint.classList.remove('d-none');
      window.refreshGcmSelect(capSelect);
      return;
    }

    capHint.classList.add('d-none');
    try {
      const res = await window.axios.get('/api/v1/asset-capacity-categories', { params: { for_type: type } });
      if (seq !== capReqSeq) return; // a newer change already fired
      res.data.data.forEach((c) =>
        capSelect.add(new Option(`${c.name} (${c.capacity_cbm} CBM / ${c.capacity_ton} TON)`, c.id))
      );
      if (keepValue && capSelect.querySelector(`option[value="${keepValue}"]`)) capSelect.value = keepValue;
    } catch (e) {
      // leave the placeholder only — the capacity is optional here
    }

    window.refreshGcmSelect(capSelect);
  }

  function toggleEmbedded() {
    const { has } = embeddedInputs();
    typeWrapper.classList.toggle('d-none', !has);
    capacityWrapper.classList.toggle('d-none', !has);
    if (!has) {
      form.querySelectorAll('input[name="default_embedded_container_type"]').forEach((r) => {
        r.checked = false;
      });
      capSelect.value = '';
    }
    refreshCapacities(has ? undefined : '');
  }

  form.querySelectorAll('input[name="default_has_embedded_container"]').forEach((r) => r.addEventListener('change', toggleEmbedded));
  // Switching container <-> tank can invalidate the picked capacity, so the
  // list is rebuilt and the old pick only survives if it still fits.
  form.querySelectorAll('input[name="default_embedded_container_type"]').forEach((r) =>
    r.addEventListener('change', () => refreshCapacities())
  );

  function validate() {
    clearAllErrors();
    let firstInvalid = null;
    form.querySelectorAll('input[type="text"][required]').forEach((el) => {
      if (!el.value.trim()) {
        showFieldError(el, t.required || 'This field is required.');
        if (!firstInvalid) firstInvalid = el;
      }
    });

    const { has, type } = embeddedInputs();
    if (has && !type) {
      const typeEl = form.querySelector('input[name="default_embedded_container_type"]');
      showFieldError(typeEl, t.required || 'This field is required.');
      if (!firstInvalid) firstInvalid = typeEl;
    }

    if (firstInvalid) firstInvalid.focus();
    return !firstInvalid;
  }

  function applyServerErrors(errors) {
    const unmapped = [];
    Object.entries(errors).forEach(([key, messages]) => {
      const el = form.querySelector(`[name="${key}"]`);
      if (el) showFieldError(el, messages[0]);
      else unmapped.push(messages[0]);
    });
    if (unmapped.length) {
      errorBox.textContent = unmapped.join(' ');
      errorBox.classList.remove('d-none');
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    const defaults = embeddedInputs();
    const payload = {
      name_en: form.querySelector('#name_en').value.trim(),
      name_ar: form.querySelector('#name_ar').value.trim(),
      default_has_embedded_container: defaults.has,
      default_embedded_container_type: defaults.has ? defaults.type : null,
      default_embedded_asset_capacity_category_id: defaults.has && capSelect.value ? Number(capSelect.value) : null
    };

    const request =
      mode === 'edit'
        ? window.axios.patch(`/api/v1/vehicle-categories/${id}`, payload, { onUploadProgress: window.gcmBusy.onUploadProgress })
        : window.axios.post('/api/v1/vehicle-categories', payload, { onUploadProgress: window.gcmBusy.onUploadProgress });

    request
      .then(() => {
        window.location.href = `${t.list_url || '/app/vehicle-category/list'}?${mode === 'edit' ? 'saved' : 'created'}=1`;
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
        }
      });
  });

  if (mode === 'edit') {
    window.axios
      .get(`/api/v1/vehicle-categories/${id}`)
      .then((res) => {
        const c = res.data.data;
        form.querySelector('#name_en').value = c.name_en;
        form.querySelector('#name_ar').value = c.name_ar;

        form.querySelector(`input[name="default_has_embedded_container"][value="${c.default_has_embedded_container ? '1' : '0'}"]`).checked = true;
        if (c.default_embedded_container_type) {
          form.querySelector(`input[name="default_embedded_container_type"][value="${c.default_embedded_container_type}"]`).checked = true;
        }
        typeWrapper.classList.toggle('d-none', !c.default_has_embedded_container);
        capacityWrapper.classList.toggle('d-none', !c.default_has_embedded_container);

        return refreshCapacities(c.default_embedded_asset_capacity_category_id || '');
      })
      .then(() => {
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
