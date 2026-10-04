/**
 * Shared engine for the create & edit contractor-user forms (both render the
 * `tenant.users._contractor-user-form` Blade partial) — FRD V01.14 §1.4: a
 * user of one contractor company (name, email, mobile, password, photo, the
 * contractor, status, additional data).
 *
 * Handles: the Quill editor, the live-search contractor picker, inline
 * per-field validation (client side + mapping of the server's 422 errors) and
 * the multipart submit with the shared busy overlay / file-size guard.
 *
 * On create the contractor list is every ACTIVE contractor (a deactivated one
 * is no longer offered for new work). On edit the account's current contractor
 * stays selectable even if it was deactivated since — otherwise saving would
 * silently move it. Every field, the email included, is editable; status goes
 * through its own endpoint after the save, like the other account forms.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initContractorUserForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';

export function initContractorUserForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.contractorUserFormTranslations || {};

  const form = document.getElementById('contractorUserForm');
  if (!form) return;

  const errorBox = document.getElementById('contractor-user-form-error');
  const loading = document.getElementById('contractor-user-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const contractorSelect = form.querySelector('#contractor_id');

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  // The account being edited (edit mode) — to know whether its status changed.
  let editing = null;

  // ---------------------------------------------------------------- errors
  function feedbackEl(el) {
    const wrapper = el.closest('[class*="col-"]') || el.parentElement;
    return wrapper ? wrapper.querySelector('[data-feedback]') : null;
  }

  function showFieldError(el, msg) {
    if (el.type === 'radio' && el.name) {
      form.querySelectorAll(`input[name="${el.name}"]`).forEach((r) => r.classList.add('is-invalid'));
    } else if (el.matches('input, select, textarea')) {
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
  // A change made through Select2 is a jQuery event, not a native one.
  window.$(contractorSelect).on('change', () => clearFieldError(contractorSelect));

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
      const base = key.replace(/\.\d+$/, '');
      const el = form.querySelector(`[name="${base}"]`);
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

  function showFatal(error) {
    errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
    errorBox.classList.remove('d-none');
    window.scrollTo(0, 0);
  }

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    // Server limit: 2 MB for the photo — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { default: 2048 });
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

    const newStatus = data.get('status');
    let url = '/api/v1/contractor-users';
    if (mode === 'edit') {
      url = `/api/v1/contractor-users/${id}`;
      data.set('_method', 'PATCH');
      // Status has its own endpoint; a blank password means "unchanged".
      data.delete('status');
      if (!data.get('password')) {
        data.delete('password');
        data.delete('password_confirmation');
      }
    }

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    let request = window.axios.post(url, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: window.gcmBusy.onUploadProgress
    });

    if (mode === 'edit' && editing && newStatus !== editing.status) {
      request = request.then(() => window.axios.patch(`/api/v1/users/${id}/status`, { status: newStatus }));
    }

    request
      .then(() => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/user/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/user/list'}?created=1`;
        }
      })
      .catch((error) => {
        window.gcmBusy.stop();
        submitBtn.disabled = false;
        const res = error.response;
        if (res && res.status === 422 && res.data && res.data.errors) {
          applyServerErrors(res.data.errors);
        } else {
          showFatal(error);
        }
      });
  });

  // --------------------------------------------------------- mode bootstrap
  function fillContractors(contractors, selectedId) {
    contractorSelect.innerHTML = '<option value=""></option>';
    contractors.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.id;
      // Names are user input — textContent, never innerHTML.
      opt.textContent = `${c.name} (${c.code})`;
      contractorSelect.appendChild(opt);
    });
    if (selectedId) contractorSelect.value = String(selectedId);
    window.refreshGcmSelect(contractorSelect);
  }

  function showCurrentPhoto(url) {
    const el = document.getElementById('current-photo');
    if (!el || !url) return;
    el.innerHTML = '';
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = t.current_file || 'Current file';
    el.appendChild(link);
    if (t.keep_file_hint) {
      const hint = document.createElement('span');
      hint.className = 'text-muted ms-2';
      hint.textContent = t.keep_file_hint;
      el.appendChild(hint);
    }
    el.classList.remove('d-none');
  }

  function prefill(u) {
    const set = (field, value) => {
      const el = form.querySelector(`#${field}`);
      if (el && value != null) el.value = value;
    };
    set('name', u.name);
    set('email', u.email);
    set('phone', u.phone);
    quill.root.innerHTML = u.additional_data || '';
    form.querySelector(`input[name="status"][value="${u.status}"]`).checked = true;
    showCurrentPhoto(u.photo_url);

    const stamp = document.getElementById('contractor-user-last-updated');
    if (stamp && u.updated_by_name && t.last_updated_by) {
      const at = u.updated_at ? new Date(u.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', u.updated_by_name).replace(':at', at);
    }
  }

  window.initGcmSelects(form);

  if (mode === 'create') {
    window
      .gcmFetchAll('/api/v1/contractors', { operational_status: 'active', sort_by: 'name' })
      .then((contractors) => {
        const preselected = new URLSearchParams(window.location.search).get('contractor_id');
        fillContractors(contractors, preselected && contractors.some((c) => String(c.id) === preselected) ? preselected : null);
      })
      .catch(showFatal);
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('contractor-user-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/user/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/users/${id}`)
      .then((res) => {
        const user = res.data.data;
        if (!user.roles.includes('contractor_user')) {
          loading.classList.add('d-none');
          errorBox.textContent = t.not_a_contractor_user || 'This account is not a contractor user.';
          errorBox.classList.remove('d-none');
          return null;
        }
        editing = user;
        prefill(user);

        return window.gcmFetchAll('/api/v1/contractors', { sort_by: 'name' }).then((contractors) => {
          fillContractors(
            contractors.filter((c) => c.operational_status === 'active' || c.id === user.contractor_id),
            user.contractor_id
          );
          loading.classList.add('d-none');
          form.classList.remove('d-none');
        });
      })
      .catch((error) => {
        loading.classList.add('d-none');
        showFatal(error);
      });
  }
}
