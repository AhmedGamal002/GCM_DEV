/**
 * Shared engine for the create & edit client-company forms (both render the
 * `tenant.companies._form` Blade partial). Handles: the Quill editor, inline
 * per-field validation (client-side + mapping of the server's 422 errors)
 * and the multipart submit (logo + three attachments) with the shared
 * busy overlay / upload progress / file-size guard from busy.js.
 *
 * On edit every field is editable (FRD V01.14 §1.11.4) except the short
 * name, which the company ID (ALN-0001) is built from — it is shown
 * disabled. A file input left empty keeps the stored file, and the current
 * files are linked next to their inputs.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initCompanyForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const PREFIX_MSG = 'The short name must be exactly 3 English letters.';
const DIGITS_MSG = 'The number must contain digits only.';

export function initCompanyForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.companyFormTranslations || {};

  const form = document.getElementById('companyForm');
  if (!form) return;

  const errorBox = document.getElementById('company-form-error');
  const loading = document.getElementById('company-form-loading');
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

  // The short name is 3 English letters, always shown upper case.
  const prefixInput = form.querySelector('#prefix');
  prefixInput.addEventListener('input', () => {
    prefixInput.value = prefixInput.value.replace(/[^A-Za-z]/g, '').toUpperCase();
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
      if (el.disabled || el.closest('.d-none')) return;

      if (el.type === 'radio') {
        if (seenRadioGroups.has(el.name)) return;
        seenRadioGroups.add(el.name);
        if (!form.querySelector(`input[name="${el.name}"]:checked`)) fail(el, t.required || REQUIRED_MSG);
        return;
      }
      if (!el.value.trim()) fail(el, t.required || REQUIRED_MSG);
    });

    if (prefixInput.value && !/^[A-Z]{3}$/.test(prefixInput.value)) fail(prefixInput, t.prefix_format || PREFIX_MSG);

    ['contract_number', 'cr_number', 'tax_number'].forEach((name) => {
      const el = form.querySelector(`#${name}`);
      if (el.value && !/^[0-9]+$/.test(el.value)) fail(el, t.digits_only || DIGITS_MSG);
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

    // Server limits: 2 MB logo, 4 MB per attachment — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { default: 4096, logo: 2048 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      window.scrollTo(0, 0);
      return;
    }

    const data = new FormData(form);
    data.set('additional_data', quill.getLength() > 1 ? quill.root.innerHTML : '');
    for (const [key, value] of Array.from(data.entries())) {
      if (value instanceof File && value.size === 0 && value.name === '') data.delete(key);
    }

    let url = '/api/v1/companies';
    if (mode === 'edit') {
      url = `/api/v1/companies/${id}`;
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
          window.location.href = `${t.view_url_base || '/app/company/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/company/list'}?created=1`;
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
  function showCurrentFile(elId, url, isImage) {
    const el = document.getElementById(elId);
    if (!el || !url) return;
    const label = t.current_file || 'Current file';
    el.innerHTML = '';
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = label;
    el.appendChild(link);
    if (t.keep_file_hint) {
      const hint = document.createElement('span');
      hint.className = 'text-muted ms-2';
      hint.textContent = t.keep_file_hint;
      el.appendChild(hint);
    }
    el.classList.remove('d-none');
  }

  function prefill(c) {
    const set = (field, value) => {
      const el = form.querySelector(`#${field}`);
      if (el && value != null) el.value = value;
    };
    set('name', c.name);
    set('prefix', c.prefix);
    set('business_sector', c.business_sector);
    set('phone', c.phone);
    set('email', c.email);
    set('address', c.address);
    set('location_url', c.location_url);
    set('contract_number', c.contract && c.contract.number);
    set('contract_start_date', c.contract && c.contract.start_date);
    set('contract_end_date', c.contract && c.contract.end_date);
    set('cr_number', c.commercial_registration && c.commercial_registration.number);
    set('tax_number', c.tax_registration && c.tax_registration.number);
    quill.root.innerHTML = c.additional_data || '';

    showCurrentFile('current-logo', c.logo_url);
    showCurrentFile('current-contract', c.contract && c.contract.download_url);
    showCurrentFile('current-cr', c.commercial_registration && c.commercial_registration.download_url);
    showCurrentFile('current-tax', c.tax_registration && c.tax_registration.download_url);

    const codeEl = document.getElementById('company-code');
    if (codeEl) codeEl.textContent = c.code;

    const stamp = document.getElementById('company-last-updated');
    if (stamp && c.updated_by_name && t.last_updated_by) {
      const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', c.updated_by_name).replace(':at', at);
    }
  }

  // FRD §1.11 "حساب ممثل العميل": one of this company's active project
  // managers. The saved representative stays selectable even if it is no
  // longer listed (e.g. put on vacation since) so saving doesn't drop it.
  function loadRepresentatives(company) {
    const select = form.querySelector('#representative_id');
    if (!select) return;

    window.$(select).on('change', () => clearFieldError(select));

    const fill = (users) => {
      select.innerHTML = '<option value=""></option>';
      const seen = new Set();
      const add = (u) => {
        if (seen.has(u.id)) return;
        seen.add(u.id);
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.name} (${u.email})`;
        select.appendChild(opt);
      };
      users.forEach(add);
      if (company.representative) add(company.representative);
      select.value = company.representative ? String(company.representative.id) : '';
      window.initGcmSelects(form);
      window.refreshGcmSelect(select);
    };

    window
      .gcmFetchAll('/api/v1/users', { company_id: company.id, role: 'client_project_manager', status: 'active', sort_by: 'name' })
      .then(fill)
      .catch(() => fill([]));
  }

  // Create: the representative picker is shown locked (a new company has no
  // accounts yet) — still a Select2 like every other select.
  if (mode === 'create') {
    window.initGcmSelects(form);
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('company-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/company/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/companies/${id}`)
      .then((res) => {
        const company = res.data.data;
        prefill(company);
        loadRepresentatives(company);
        loading.classList.add('d-none');
        form.classList.remove('d-none');
        onLoaded(company);
      })
      .catch((error) => {
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }
}
