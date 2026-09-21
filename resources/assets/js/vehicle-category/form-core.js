/**
 * Shared engine for the create & edit vehicle-category forms (both render
 * `tenant.vehicle-categories._form`): two required fields, name in English
 * and in Arabic. Lives in a subdirectory so the `resources/assets/js/*.js`
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
    if (e.target.matches('input')) {
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
    form.querySelectorAll('input[required]').forEach((el) => {
      if (!el.value.trim()) {
        showFieldError(el, t.required || 'This field is required.');
        if (!firstInvalid) firstInvalid = el;
      }
    });
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

    const payload = {
      name_en: form.querySelector('#name_en').value.trim(),
      name_ar: form.querySelector('#name_ar').value.trim()
    };

    const request =
      mode === 'edit'
        ? window.axios.patch(`/api/v1/vehicle-categories/${id}`, payload)
        : window.axios.post('/api/v1/vehicle-categories', payload);

    request
      .then(() => {
        window.location.href = `${t.list_url || '/app/vehicle-category/list'}?${mode === 'edit' ? 'saved' : 'created'}=1`;
      })
      .catch((error) => {
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
