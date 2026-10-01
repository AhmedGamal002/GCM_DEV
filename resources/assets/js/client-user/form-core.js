/**
 * Shared engine for the create & edit client-account forms (both render the
 * `tenant.users._client-form` Blade partial) — FRD V01.14 §1.4: a Project
 * Manager / Project Auditor of one client company, with access to all of the
 * company's projects (current and future) or a chosen set.
 *
 * Handles: the Quill editor, the live-search company picker, the project
 * checklist of the selected company, inline per-field validation (client
 * side + mapping of the server's 422 errors) and the multipart submit with
 * the shared busy overlay / file-size guard.
 *
 * On create the company list is every ACTIVE company (a deactivated one "is
 * no longer offered for new work") and only active projects are offered. On
 * edit the account's current company and already-assigned projects stay
 * selectable even if they were deactivated since — otherwise saving would
 * silently drop them. The email is immutable; status goes through its own
 * endpoint after the save, like the other account forms.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry; the two thin entry files import
 * `initClientUserForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const CLIENT_ROLES = ['client_project_manager', 'client_project_auditor'];

export function initClientUserForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const t = window.clientUserFormTranslations || {};

  const form = document.getElementById('clientUserForm');
  if (!form) return;

  const errorBox = document.getElementById('client-user-form-error');
  const loading = document.getElementById('client-user-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const companySelect = form.querySelector('#company_id');
  const projectsPicker = document.getElementById('projects-picker');
  const projectsList = document.getElementById('projects-list');

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  // The account being edited (edit mode) — its assigned projects stay listed.
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
  window.$(companySelect).on('change', () => clearFieldError(companySelect));

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

    if (selectedScope() === 'specific' && !checkedProjectIds().length) {
      fail(projectsList, t.required || REQUIRED_MSG);
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
      const base = key.replace(/\.\d+$/, '');
      const el = form.querySelector(`[name="${base}"]`) || form.querySelector(`[data-field="${base}"]`);
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

  // ------------------------------------------------------ projects checklist
  const selectedScope = () => form.querySelector('input[name="projects_scope"]:checked').value;
  const checkedProjectIds = () => Array.from(projectsList.querySelectorAll('input[type="checkbox"]:checked')).map((c) => c.value);

  function togglePicker() {
    projectsPicker.classList.toggle('d-none', selectedScope() !== 'specific');
  }
  form.querySelectorAll('input[name="projects_scope"]').forEach((r) => r.addEventListener('change', togglePicker));

  function renderProjects(projects, checkedIds) {
    projectsList.innerHTML = '';
    const assigned = new Set((checkedIds || []).map(String));

    const shown = projects.filter((p) => p.operational_status === 'active' || assigned.has(String(p.id)));
    if (!shown.length) {
      const empty = document.createElement('span');
      empty.className = 'text-muted';
      empty.textContent = t.no_projects || 'This company has no projects yet.';
      projectsList.appendChild(empty);
      return;
    }

    shown.forEach((p) => {
      const wrap = document.createElement('div');
      wrap.className = 'form-check';
      const box = document.createElement('input');
      box.type = 'checkbox';
      box.className = 'form-check-input';
      box.id = `project-${p.id}`;
      box.value = p.id;
      box.checked = assigned.has(String(p.id));
      const label = document.createElement('label');
      label.className = 'form-check-label';
      label.htmlFor = box.id;
      // Names are user input — textContent, never innerHTML.
      label.textContent = `${p.name} (${p.code})` + (p.operational_status === 'deactivated' ? ` — ${t.deactivated || 'Deactivated'}` : '');
      wrap.append(box, label);
      projectsList.appendChild(wrap);
    });
  }

  function loadProjects(companyId, checkedIds) {
    if (!companyId) {
      projectsList.innerHTML = '';
      const hint = document.createElement('span');
      hint.className = 'text-muted';
      hint.textContent = t.select_company_first || 'Select a company first.';
      projectsList.appendChild(hint);
      return Promise.resolve();
    }
    return window
      .gcmFetchAll('/api/v1/projects', { company_id: companyId, sort_by: 'name' })
      .then((projects) => renderProjects(projects, checkedIds))
      .catch(showFatal);
  }

  // A different company means a different set of projects; ticks from the
  // previous company are meaningless, so they are dropped.
  window.$(companySelect).on('change', () => loadProjects(companySelect.value, []));

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    // Server limit: 2 MB per image — fail now, not after the upload.
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
    if (selectedScope() === 'specific') {
      checkedProjectIds().forEach((pid) => data.append('project_ids[]', pid));
    }

    const newStatus = data.get('status');
    let url = '/api/v1/client-users';
    if (mode === 'edit') {
      url = `/api/v1/client-users/${id}`;
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
      .then((res) => {
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
  function fillCompanies(companies, selectedId) {
    companySelect.innerHTML = '<option value=""></option>';
    companies.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = `${c.name} (${c.code})`;
      companySelect.appendChild(opt);
    });
    if (selectedId) companySelect.value = String(selectedId);
    window.refreshGcmSelect(companySelect);
  }

  function showCurrentImage(elId, url) {
    const el = document.getElementById(elId);
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

    const role = u.roles.find((r) => CLIENT_ROLES.includes(r));
    const roleInput = form.querySelector(`input[name="role"][value="${role}"]`);
    if (roleInput) roleInput.checked = true;
    form.querySelector(`input[name="projects_scope"][value="${u.projects_scope || 'all'}"]`).checked = true;
    form.querySelector(`input[name="status"][value="${u.status}"]`).checked = true;
    togglePicker();

    showCurrentImage('current-signature', u.signature_url);
    showCurrentImage('current-stamp', u.stamp_url);

    const stamp = document.getElementById('client-user-last-updated');
    if (stamp && u.updated_by_name && t.last_updated_by) {
      const at = u.updated_at ? new Date(u.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', u.updated_by_name).replace(':at', at);
    }
  }

  if (mode === 'create') {
    window.initGcmSelects(form);
    loadProjects(null);

    window
      .gcmFetchAll('/api/v1/companies', { operational_status: 'active', sort_by: 'name' })
      .then((companies) => {
        const preselected = new URLSearchParams(window.location.search).get('company_id');
        fillCompanies(companies, preselected && companies.some((c) => String(c.id) === preselected) ? preselected : null);
        if (companySelect.value) loadProjects(companySelect.value, []);
      })
      .catch(showFatal);
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('client-user-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/user/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.initGcmSelects(form);

    window.axios
      .get(`/api/v1/users/${id}`)
      .then((res) => {
        const user = res.data.data;
        if (!user.roles.some((r) => CLIENT_ROLES.includes(r))) {
          loading.classList.add('d-none');
          errorBox.textContent = t.not_a_client || 'This account is not a client account.';
          errorBox.classList.remove('d-none');
          return null;
        }
        editing = user;
        prefill(user);

        return window.gcmFetchAll('/api/v1/companies', { sort_by: 'name' }).then((companies) => {
          fillCompanies(
            companies.filter((c) => c.operational_status === 'active' || c.id === user.company_id),
            user.company_id
          );
          return loadProjects(user.company_id, (user.projects || []).map((p) => p.id));
        }).then(() => {
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
