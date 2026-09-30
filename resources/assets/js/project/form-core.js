/**
 * Shared engine for the create & edit project forms (both render the
 * `tenant.projects._form` Blade partial). Handles: the Quill editor, the
 * live-search company picker (create only), inline per-field validation
 * (client-side + mapping of the server's 422 errors) and the JSON submit
 * with the shared busy overlay.
 *
 * On create the company list is every ACTIVE client company (a deactivated
 * one is "no longer offered for new work"), and `?company_id=` from a
 * company's page preselects it. On edit every field is editable (FRD
 * V01.14 §1.12.4) except the company — the project ID (ALN-P0001) is built
 * from it — which is shown disabled.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initProjectForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';

export function initProjectForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.projectFormTranslations || {};

  const form = document.getElementById('projectForm');
  if (!form) return;

  const errorBox = document.getElementById('project-form-error');
  const loading = document.getElementById('project-form-loading');
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

  // ----------------------------------------------------- client validation
  function validate() {
    clearAllErrors();
    let firstInvalid = null;
    const fail = (el, msg) => {
      showFieldError(el, msg);
      if (!firstInvalid) firstInvalid = el;
    };

    form.querySelectorAll('input[required], select[required], textarea[required]').forEach((el) => {
      if (el.disabled || el.closest('.d-none') || el.type === 'radio') return;
      if (!el.value.trim()) fail(el, t.required || REQUIRED_MSG);
    });

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
      const el = form.querySelector(`[name="${key}"]`);
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
    if (firstInvalid && firstInvalid.scrollIntoView) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else window.scrollTo(0, 0);
  }

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    const data = {};
    new FormData(form).forEach((value, key) => {
      data[key] = value;
    });
    data.additional_data = quill.getLength() > 1 ? quill.root.innerHTML : '';
    // Empty optional inputs go as null, not '' — same as a field never filled in.
    Object.keys(data).forEach((key) => {
      if (data[key] === '') data[key] = null;
    });

    submitBtn.disabled = true;
    window.gcmBusy.start();

    const request = mode === 'edit' ? window.axios.patch(`/api/v1/projects/${id}`, data) : window.axios.post('/api/v1/projects', data);

    request
      .then(() => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/project/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/project/list'}?created=1`;
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
  function prefill(p) {
    const set = (field, value) => {
      const el = form.querySelector(`#${field}`);
      if (el && value != null) el.value = value;
    };
    set('name', p.name);
    set('operational_region', p.operational_region);
    set('phone', p.phone);
    set('email', p.email);
    set('address', p.address);
    set('location_url', p.location_url);
    set('company_name', p.company && p.company.name);
    quill.root.innerHTML = p.additional_data || '';

    const codeEl = document.getElementById('project-code');
    if (codeEl) codeEl.textContent = p.code;

    const stamp = document.getElementById('project-last-updated');
    if (stamp && p.updated_by_name && t.last_updated_by) {
      const at = p.updated_at ? new Date(p.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', p.updated_by_name).replace(':at', at);
    }
  }

  if (mode === 'create') {
    const companySelect = form.querySelector('#company_id');
    // A change made through Select2 is a jQuery event, not a native one.
    window.$(companySelect).on('change', () => clearFieldError(companySelect));
    window.initGcmSelects(form);

    window
      .gcmFetchAll('/api/v1/companies', { operational_status: 'active', sort_by: 'name' })
      .then((companies) => {
        companySelect.innerHTML = '<option value=""></option>';
        companies.forEach((c) => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = `${c.name} (${c.code})`;
          companySelect.appendChild(opt);
        });

        const preselected = new URLSearchParams(window.location.search).get('company_id');
        if (preselected && companies.some((c) => String(c.id) === preselected)) {
          companySelect.value = preselected;
        }
        window.refreshGcmSelect(companySelect);
      })
      .catch((error) => {
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('project-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/project/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/projects/${id}`)
      .then((res) => {
        const project = res.data.data;
        prefill(project);
        loading.classList.add('d-none');
        form.classList.remove('d-none');
        onLoaded(project);
      })
      .catch((error) => {
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }
}
