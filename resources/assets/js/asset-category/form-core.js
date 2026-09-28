/**
 * Shared engine for the create & edit asset-capacity-category forms (both
 * render `tenant.asset-categories._form`). Small form — name, applies_to,
 * CBM, TON, notes. On edit the FRD allows ONLY the name to change, so
 * every other field is rendered pre-filled but disabled.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up; the thin entry files import `initAssetCategoryForm`.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';

export function initAssetCategoryForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.assetCategoryFormTranslations || {};

  const form = document.getElementById('assetCategoryForm');
  if (!form) return;

  const errorBox = document.getElementById('asset-category-form-error');
  const loading = document.getElementById('asset-category-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');

  function feedbackEl(el) {
    const wrapper = el.closest('[class*="col-"]') || el.parentElement;
    return wrapper ? wrapper.querySelector('[data-feedback]') : null;
  }

  function showFieldError(el, msg) {
    el.classList.add('is-invalid');
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

  form.addEventListener('input', (e) => {
    if (e.target.matches('input, select, textarea')) {
      e.target.classList.remove('is-invalid');
      const fb = feedbackEl(e.target);
      if (fb) {
        fb.textContent = '';
        fb.classList.add('d-none');
      }
    }
  });

  function validate() {
    clearAllErrors();
    let firstInvalid = null;
    form.querySelectorAll('input[required], select[required]').forEach((el) => {
      if (el.disabled) return;
      if (!el.value) {
        showFieldError(el, t.required || REQUIRED_MSG);
        if (!firstInvalid) firstInvalid = el;
      }
    });
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus({ preventScroll: true });
    }
    return !firstInvalid;
  }

  function applyServerErrors(errors) {
    let firstInvalid = null;
    const unmapped = [];
    Object.entries(errors).forEach(([key, messages]) => {
      const el = form.querySelector(`[name="${key.split('.')[0]}"]`);
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
    if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else window.scrollTo(0, 0);
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    let url = '/api/v1/asset-capacity-categories';
    let method = 'post';
    let payload;

    if (mode === 'edit') {
      url = `/api/v1/asset-capacity-categories/${id}`;
      method = 'patch';
      payload = { name: form.querySelector('#name').value };
    } else {
      payload = {
        name: form.querySelector('#name').value,
        applies_to: form.querySelector('#applies_to').value,
        capacity_cbm: form.querySelector('#capacity_cbm').value,
        capacity_ton: form.querySelector('#capacity_ton').value,
        additional_data: form.querySelector('#additional_data').value || null
      };
    }

    window.axios[method](url, payload, { onUploadProgress: window.gcmBusy.onUploadProgress })
      .then(() => {
        window.location.href = `${t.list_url || '/app/asset-category/list'}?created=1`;
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

  if (mode === 'edit') {
    const cancel = document.getElementById('asset-category-form-cancel');
    if (cancel && t.cancel) cancel.textContent = t.cancel;

    window.axios
      .get(`/api/v1/asset-capacity-categories/${id}`)
      .then((res) => {
        const c = res.data.data;
        form.querySelector('#name').value = c.name;
        form.querySelector('#applies_to').value = c.applies_to;
        form.querySelector('#capacity_cbm').value = c.capacity_cbm;
        form.querySelector('#capacity_ton').value = c.capacity_ton;
        form.querySelector('#additional_data').value = c.additional_data || '';

        form.querySelectorAll('input, select, textarea').forEach((el) => {
          if (el.id !== 'name') el.disabled = true;
        });

        const stamp = document.getElementById('asset-category-last-updated');
        if (stamp && c.updated_by_name && t.last_updated_by) {
          const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
          stamp.textContent = t.last_updated_by.replace(':name', c.updated_by_name).replace(':at', at);
        }

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
