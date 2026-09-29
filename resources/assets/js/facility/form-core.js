/**
 * Shared engine for the create & edit facility forms (both render the
 * `tenant.facilities._form` Blade partial). Handles: the recycling-efficiency
 * field that only exists for a recycling facility, the Quill editor, inline
 * per-field validation (client-side + mapping of the server's 422 errors),
 * and the multipart submit (logo + contract attachment) with the busy
 * overlay and the pre-upload file-size guard.
 *
 * On edit the prefix is read-only, and the environmental service +
 * recycling efficiency are locked (disabled) when the API says the facility
 * is `in_use` — disabled inputs aren't submitted, so the server keeps the
 * stored values.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initFacilityForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';

export function initFacilityForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.facilityFormTranslations || {};

  const form = document.getElementById('facilityForm');
  if (!form) return;

  const errorBox = document.getElementById('facility-form-error');
  const loading = document.getElementById('facility-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const efficiencyGroup = document.getElementById('recycling-efficiency-group');
  const efficiencyInput = document.getElementById('recycling_efficiency');
  const prefixInput = document.getElementById('prefix');

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

  // ------------------------------------------------- recycling-only efficiency
  function selectedService() {
    const checked = form.querySelector('input[name="environmental_service"]:checked');
    return checked ? checked.value : null;
  }

  function syncEfficiencyVisibility() {
    const isRecycle = selectedService() === 'recycle';
    efficiencyGroup.classList.toggle('d-none', !isRecycle);
    efficiencyInput.required = isRecycle;
    if (!isRecycle) efficiencyInput.value = '';
  }

  form.querySelectorAll('input[name="environmental_service"]').forEach((r) =>
    r.addEventListener('change', syncEfficiencyVisibility)
  );

  // The prefix is always upper-case letters — same as the server stores it.
  if (prefixInput) {
    prefixInput.addEventListener('input', () => {
      prefixInput.value = prefixInput.value.toUpperCase().replace(/[^A-Z]/g, '');
    });
  }

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
      if (!String(el.value).trim()) fail(el, t.required || REQUIRED_MSG);
    });

    if (prefixInput && !prefixInput.disabled && !prefixInput.readOnly && prefixInput.value && !/^[A-Z]{3}$/.test(prefixInput.value)) {
      fail(prefixInput, t.prefix_format || 'The prefix must be exactly 3 English letters.');
    }

    if (!efficiencyInput.disabled && !efficiencyGroup.classList.contains('d-none') && efficiencyInput.value !== '') {
      const v = Number(efficiencyInput.value);
      if (Number.isNaN(v) || v < 0 || v > 100) fail(efficiencyInput, t.efficiency_range || 'Enter a percentage between 0 and 100.');
    }

    const start = form.querySelector('#contract_start').value;
    const end = form.querySelector('#contract_end').value;
    if (start && end && end < start) fail(form.querySelector('#contract_end'), t.date_order || "The end date can't be before the start date.");

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
      const el = form.querySelector(`[name="${key.split('.')[0]}"]`);
      if (el) {
        // A hidden efficiency field can't show its message — reveal the group.
        if (el === efficiencyInput) efficiencyGroup.classList.remove('d-none');
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

    // Server limits: 2 MB logo, 4 MB contract attachment — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { logo: 2048, contract_attachment: 4096, default: 4096 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      window.scrollTo(0, 0);
      return;
    }

    // Disabled inputs (the locked service/efficiency) aren't part of
    // FormData — exactly what we want, the server keeps the stored values.
    const data = new FormData(form);
    data.set('additional_data', quill.getLength() > 1 ? quill.root.innerHTML : '');
    for (const [key, value] of Array.from(data.entries())) {
      if (value instanceof File && value.size === 0 && value.name === '') data.delete(key);
    }
    if (mode === 'edit') data.delete('prefix');

    let url = '/api/v1/facilities';
    if (mode === 'edit') {
      url = `/api/v1/facilities/${id}`;
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
          window.location.href = `${t.view_url_base || '/app/facility/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/facility/list'}?created=1`;
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
  function showCurrentFile(elId, html) {
    const el = document.getElementById(elId);
    el.innerHTML = html;
    el.classList.remove('d-none');
  }

  function esc(v) {
    const d = document.createElement('div');
    d.textContent = v == null ? '' : v;
    return d.innerHTML;
  }

  function prefill(f) {
    form.querySelector('#name').value = f.name;
    prefixInput.value = f.prefix;
    prefixInput.readOnly = true;
    prefixInput.classList.add('bg-lighter');

    const service = form.querySelector(`input[name="environmental_service"][value="${f.environmental_service}"]`);
    if (service) service.checked = true;
    if (f.recycling_efficiency !== null && f.recycling_efficiency !== undefined) efficiencyInput.value = f.recycling_efficiency;
    syncEfficiencyVisibility();

    form.querySelector('#address').value = f.address || '';
    form.querySelector('#location_url').value = f.location_url || '';
    form.querySelector('#contract_number').value = f.contract_number || '';
    form.querySelector('#contract_start').value = f.contract_start || '';
    form.querySelector('#contract_end').value = f.contract_end || '';
    quill.root.innerHTML = f.additional_data || '';

    if (f.logo_url) {
      showCurrentFile(
        'logo_current',
        `${esc(t.current_file || 'Current file')}: <a href="${esc(f.logo_url)}" target="_blank" rel="noopener">${esc(f.logo_url.split('/').pop())}</a> — ${esc(t.replace_hint || '')}`
      );
    }
    if (f.has_contract_attachment) {
      showCurrentFile(
        'contract_attachment_current',
        `${esc(t.current_file || 'Current file')}: <a href="${esc(`${t.contract_url_base || '/api/v1/facilities'}/${f.id}/contract`)}" target="_blank" rel="noopener"><i class="ti ti-paperclip"></i></a> — ${esc(t.replace_hint || '')}`
      );
    }

    // Locked once a sub-service or trip uses the facility.
    if (f.in_use) {
      form.querySelectorAll('input[name="environmental_service"], #recycling_efficiency').forEach((el) => {
        el.disabled = true;
      });
      document.getElementById('facility-locked-alert').classList.remove('d-none');
    }

    const stamp = document.getElementById('facility-last-updated');
    if (stamp && f.updated_by_name && t.last_updated_by) {
      const at = f.updated_at ? new Date(f.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', f.updated_by_name).replace(':at', at);
    }
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('facility-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/facility/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/facilities/${id}`)
      .then((res) => {
        prefill(res.data.data);
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
