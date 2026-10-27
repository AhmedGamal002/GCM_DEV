/**
 * Shared engine for the create & edit contractor forms (both render the
 * `tenant.contractors._form` Blade partial). Handles: the Quill editor, the
 * "Certified services" checkboxes, inline per-field validation (client-side +
 * mapping of the server's 422 errors) and the multipart submit (logo + three
 * attachments) with the shared busy overlay / upload progress / file-size
 * guard from busy.js.
 *
 * On edit every field is editable (FRD V01.14 §1.10.4) except the short
 * name, which the contractor ID (ALN-C0001) is built from — it is shown
 * disabled. A file input left empty keeps the stored file, and the current
 * files are linked next to their inputs.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the entry files import
 * `initContractorForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const PREFIX_MSG = 'The short name must be exactly 3 English letters.';
const DIGITS_MSG = 'The number must contain digits only.';

export function initContractorForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.contractorFormTranslations || {};

  const form = document.getElementById('contractorForm');
  if (!form) return;

  const errorBox = document.getElementById('contractor-form-error');
  const loading = document.getElementById('contractor-form-loading');
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
  // Say so on the spot when the short name is already taken (not only after Submit).
  const prefixCheck = window.gcmPrefixCheck(prefixInput, { url: '/api/v1/contractors/prefix-available', message: t.prefix_taken });

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
    else if (prefixCheck.isTaken()) fail(prefixInput, t.prefix_taken);

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
    // No checkbox ticked = "supports every service" — still send the key so an edit clears the old list.
    if (!data.has('main_service_ids[]')) data.set('main_service_ids', '');
    for (const [key, value] of Array.from(data.entries())) {
      if (value instanceof File && value.size === 0 && value.name === '') data.delete(key);
    }

    let url = '/api/v1/contractors';
    if (mode === 'edit') {
      url = `/api/v1/contractors/${id}`;
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
          window.location.href = `${t.view_url_base || '/app/contractor/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/contractor/list'}?created=1`;
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
    set('trading_name', c.trading_name);
    set('phone', c.phone);
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

    const codeEl = document.getElementById('contractor-code');
    if (codeEl) codeEl.textContent = c.code;

    const stamp = document.getElementById('contractor-last-updated');
    if (stamp && c.updated_by_name && t.last_updated_by) {
      const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', c.updated_by_name).replace(':at', at);
    }
  }

  // FRD §1.10.2 "الخدمات المعتمدة": a checkbox per main service. None ticked
  // means the contractor supports every service. Deactivated services are
  // offered only when the contractor already has them, so saving doesn't
  // silently drop them.
  function loadServices(selected) {
    const holder = document.getElementById('certified-services');
    const chosen = new Set((selected || []).map((s) => String(s.id)));

    return window
      .gcmFetchAll('/api/v1/main-services', { sort_by: 'name' })
      .then((services) => services.filter((s) => s.operational_status === 'active' || chosen.has(String(s.id))))
      .catch(() => [])
      .then((services) => {
        holder.innerHTML = '';

        if (!services.length) {
          const empty = document.createElement('div');
          empty.className = 'col-12 text-muted';
          empty.textContent = t.no_services || 'No main services are available yet.';
          holder.appendChild(empty);
          return;
        }

        services.forEach((s) => {
          const col = document.createElement('div');
          col.className = 'col-md-4 col-sm-6';

          const wrap = document.createElement('div');
          wrap.className = 'form-check';

          const input = document.createElement('input');
          input.type = 'checkbox';
          input.className = 'form-check-input';
          input.name = 'main_service_ids[]';
          input.id = 'service-' + s.id;
          input.value = s.id;
          input.checked = chosen.has(String(s.id));

          const label = document.createElement('label');
          label.className = 'form-check-label';
          label.htmlFor = input.id;
          label.textContent = s.name;

          wrap.appendChild(input);
          wrap.appendChild(label);
          col.appendChild(wrap);
          holder.appendChild(col);
        });
      });
  }

  // FRD §1.10.2 "حساب ممثل المتعهد": one of this contractor's active users. The
  // saved representative stays selectable even if it is no longer listed (e.g. put
  // on vacation since) so saving doesn't drop it.
  function loadRepresentatives(contractor) {
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
        // Names are user input — textContent, never innerHTML.
        opt.textContent = `${u.name} (${u.email})`;
        select.appendChild(opt);
      };
      users.forEach(add);
      if (contractor.representative) add(contractor.representative);
      select.value = contractor.representative ? String(contractor.representative.id) : '';
      window.initGcmSelects(form);
      window.refreshGcmSelect(select);
    };

    window
      .gcmFetchAll('/api/v1/users', { contractor_id: contractor.id, role: 'contractor_user', status: 'active', sort_by: 'name' })
      .then(fill)
      .catch(() => fill([]));
  }

  // Create: the representative picker is shown locked (a new contractor has no
  // accounts yet) — still a Select2 like every other select.
  if (mode === 'create') {
    window.initGcmSelects(form);
    loadServices([]);
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('contractor-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/contractor/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/contractors/${id}`)
      .then((res) => {
        const contractor = res.data.data;
        prefill(contractor);
        loadServices(contractor.certified_services);
        loadRepresentatives(contractor);
        loading.classList.add('d-none');
        form.classList.remove('d-none');
        onLoaded(contractor);
      })
      .catch((error) => {
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }
}
