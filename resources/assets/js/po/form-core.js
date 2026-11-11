/**
 * Shared engine for the create & edit purchase-order forms (FRD V01.14 §1.13 "انشاء تعاقد جديد" /
 * "تعديل تعاقد"); both render the `tenant.purchase-orders._form` Blade partial. Handles: the company →
 * project pickers, the service blocks (add / remove, the quota method switching which fields show, the
 * "maximum cost" totals calculated live), inline validation (client side + the server's 422 errors mapped
 * back onto the field or block they name), the Quill notes and the multipart submit with the busy overlay.
 *
 * The edit lock (decision ب2) is the server's call: GET /purchase-orders/{id} returns `editability`, and the
 * form only mirrors it — with a trip on the PO everything but the quota of the existing services is
 * disabled and left out of the request; a service the ledger already points at keeps its service and method.
 * The server re-checks every one of those, so the form is a convenience, not the guard.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob doesn't pick it up as its own entry;
 * the two thin entry files import `initPoForm` from here.
 */
'use strict';

const REQUIRED_MSG = 'This field is required.';
const FIELDS = ['main_service_id', 'quota_method', 'max_trips', 'trip_cost', 'ancillary_cost_per_trip', 'alert_percent'];

export function initPoForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.poFormTranslations || {};

  const form = document.getElementById('poForm');
  if (!form) return;

  const errorBox = document.getElementById('po-form-error');
  const loading = document.getElementById('po-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const linesBox = document.getElementById('po-lines');
  const linesEmpty = document.getElementById('po-lines-empty');
  const linesFeedback = document.getElementById('po-lines-feedback');
  const addLineBtn = document.getElementById('po-add-line');
  const lineTemplate = document.getElementById('po-line-template');
  const companySelect = form.querySelector('#company_id');
  const projectSelect = form.querySelector('#project_id');
  const regionInput = form.querySelector('#region');
  const prefixEl = document.getElementById('po-prefix');

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  let companies = [];
  let projects = [];
  let mainServices = [];
  let lineCounter = 0;
  let locked = false; // a trip is recorded: only the quota is editable
  let lastProjectRegion = '';
  let existingAttachment = null;

  const lineEls = () => Array.from(linesBox.querySelectorAll('[data-line]'));
  const fieldOf = (line, name) => line.querySelector(`[data-field="${name}"]`);
  const methodOf = (line) => {
    const checked = line.querySelector('[data-field="quota_method"]:checked');
    return checked ? checked.value : 'trips_only';
  };

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
    form.querySelectorAll('[data-feedback], [data-line-feedback]').forEach((fb) => {
      fb.textContent = '';
      fb.classList.add('d-none');
    });
    linesFeedback.textContent = '';
    linesFeedback.classList.add('d-none');
    errorBox.textContent = '';
    errorBox.classList.add('d-none');
  }

  const clearOnInteract = (e) => {
    if (e.target.matches('input, select, textarea')) clearFieldError(e.target);
  };
  form.addEventListener('input', clearOnInteract);
  form.addEventListener('change', clearOnInteract);
  [companySelect, projectSelect].forEach((s) => window.$(s).on('change', () => clearFieldError(s)));

  // ------------------------------------------------------------- the money
  const money = (n) => (Number.isFinite(n) ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: false }) : '');

  function recalc(line) {
    const trips = parseFloat(fieldOf(line, 'max_trips').value);
    const cost = parseFloat(fieldOf(line, 'trip_cost').value);
    const anc = parseFloat(fieldOf(line, 'ancillary_cost_per_trip').value);
    line.querySelector('[data-total="max_trip_cost"]').value = money(Math.round(trips * cost * 100) / 100);
    line.querySelector('[data-total="max_ancillary"]').value = methodOf(line) === 'trips_and_ancillary' ? money(Math.round(trips * anc * 100) / 100) : '';
  }

  function applyMethod(line) {
    const tracks = methodOf(line) === 'trips_and_ancillary';
    line.querySelectorAll('[data-ancillary]').forEach((col) => col.classList.toggle('d-none', !tracks));
    // Required only while the method counts it (the required-marks script follows the attribute).
    const anc = fieldOf(line, 'ancillary_cost_per_trip');
    if (tracks) anc.setAttribute('required', '');
    else {
      anc.removeAttribute('required');
      clearFieldError(anc);
    }
    recalc(line);
  }

  function renumber() {
    lineEls().forEach((line, i) => {
      line.querySelector('[data-line-title]').textContent = (t.service_n || 'Service :n').replace(':n', i + 1);
    });
    linesEmpty.classList.toggle('d-none', lineEls().length > 0);
  }

  // ----------------------------------------------------------- the blocks
  function fillMainServices(select, currentId, currentName) {
    select.innerHTML = '<option value=""></option>';
    const seen = new Set();
    mainServices.forEach((s) => {
      seen.add(String(s.id));
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.name;
      select.appendChild(opt);
    });
    // A service that is no longer active (deactivated since) stays selectable on the line that holds it.
    if (currentId && !seen.has(String(currentId))) {
      const opt = document.createElement('option');
      opt.value = currentId;
      opt.textContent = currentName || `#${currentId}`;
      select.appendChild(opt);
    }
    select.value = currentId ? String(currentId) : '';
  }

  /**
   * @param {object|null} data  an existing service of the PO ({id, main_service, quota_method, …}) or null for a new block
   * @param {object|null} rule  its entry of `editability.services` (edit page), or null
   */
  function addLine(data, rule) {
    lineCounter += 1;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = lineTemplate.innerHTML.replaceAll('__N__', String(lineCounter)).trim();
    const line = wrapper.firstElementChild;
    if (data && data.id) line.dataset.lineId = data.id;
    linesBox.appendChild(line);

    const select = fieldOf(line, 'main_service_id');
    fillMainServices(select, data && data.main_service && data.main_service.id, data && data.main_service && data.main_service.name);
    window.initGcmSelects(line);
    window.$(select).on('change', () => clearFieldError(select));

    if (data) {
      line.querySelector(`[data-field="quota_method"][value="${data.quota_method}"]`).checked = true;
      fieldOf(line, 'max_trips').value = data.max_trips;
      fieldOf(line, 'trip_cost').value = data.trip_cost;
      if (data.ancillary_cost_per_trip != null) fieldOf(line, 'ancillary_cost_per_trip').value = data.ancillary_cost_per_trip;
      fieldOf(line, 'alert_percent').value = data.alert_percent;
    }

    line.querySelectorAll('[data-field="quota_method"]').forEach((r) => r.addEventListener('change', () => applyMethod(line)));
    ['max_trips', 'trip_cost', 'ancillary_cost_per_trip'].forEach((f) => fieldOf(line, f).addEventListener('input', () => recalc(line)));
    line.querySelector('[data-remove-line]').addEventListener('click', () => {
      line.remove();
      renumber();
    });

    // What the lock allows on this block (decision ب2): the quota always; the service and method only while nothing
    // points at it; removing it only while the PO has no trip and the block has no ledger history.
    if (rule) {
      fieldOf(line, 'max_trips').min = String(rule.min_max_trips || 1);
      if (!rule.can_change) {
        select.disabled = true;
        window.refreshGcmSelect(select);
        line.querySelectorAll('[data-field="quota_method"]').forEach((r) => (r.disabled = true));
        line.querySelector('[data-remove-line]').classList.add('d-none');
      }
    }

    applyMethod(line);
    renumber();
    return line;
  }

  addLineBtn.addEventListener('click', () => {
    const line = addLine(null, null);
    linesFeedback.classList.add('d-none');
    line.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  // ----------------------------------------------------- company → project
  function setPrefix() {
    const company = companies.find((c) => String(c.id) === String(companySelect.value));
    prefixEl.textContent = company ? `${company.prefix}-` : '—';
  }

  function fillProjects(selectedId) {
    projectSelect.innerHTML = '<option value=""></option>';
    projects.forEach((p) => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.name} (${p.code})`;
      projectSelect.appendChild(opt);
    });
    projectSelect.value = selectedId ? String(selectedId) : '';
    window.refreshGcmSelect(projectSelect);
  }

  function loadProjects(selectedId) {
    projects = [];
    fillProjects(null);
    if (!companySelect.value) return Promise.resolve();
    // Create: only active projects are offered for new work; edit shows them all so the saved one is always there.
    const params = { company_id: companySelect.value, sort_by: 'name' };
    if (mode === 'create') params.operational_status = 'active';
    return window
      .gcmFetchAll('/api/v1/projects', params)
      .then((list) => {
        projects = list;
        fillProjects(selectedId);
      })
      .catch(() => {});
  }

  window.$(companySelect).on('change', () => {
    setPrefix();
    loadProjects(null);
  });

  // The region follows the project until the user types their own (decision ب11: pre-filled, changeable).
  window.$(projectSelect).on('change', () => {
    const project = projects.find((p) => String(p.id) === String(projectSelect.value));
    const region = (project && project.operational_region) || '';
    if (!regionInput.value || regionInput.value === lastProjectRegion) regionInput.value = region;
    lastProjectRegion = region;
  });

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
      if (!String(el.value).trim()) fail(el, t.required || REQUIRED_MSG);
    });

    const paper = form.querySelector('#paper_number');
    if (!paper.disabled && paper.value && !/^[0-9]{1,10}$/.test(paper.value)) fail(paper, t.digits_only || 'Digits only, up to 10.');

    const from = form.querySelector('#starts_on');
    const to = form.querySelector('#ends_on');
    if (!to.disabled && from.value && to.value && to.value < from.value) fail(to, t.end_before_start || 'The end date cannot be before the start date.');

    const lines = lineEls();
    if (!lines.length) {
      linesFeedback.textContent = t.need_service || 'Add at least one service.';
      linesFeedback.classList.remove('d-none');
      if (!firstInvalid) firstInvalid = addLineBtn;
    }

    const used = new Set();
    lines.forEach((line) => {
      const select = fieldOf(line, 'main_service_id');
      if (select.value) {
        if (used.has(select.value)) fail(select, t.duplicate_service || 'This service is already in this purchase order.');
        used.add(select.value);
      }
      const max = fieldOf(line, 'max_trips');
      if (max.value && parseInt(max.value, 10) < parseInt(max.min || '1', 10)) {
        fail(max, (t.min_trips || 'Cannot be lower than the :count trips already recorded.').replace(':count', max.min));
      }
    });

    if (firstInvalid && firstInvalid.scrollIntoView) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (firstInvalid.focus) firstInvalid.focus({ preventScroll: true });
    }
    return !firstInvalid;
  }

  // -------------------------------------------------------- server errors
  // Keys come as `paper_number`, `services` or `services.0.max_trips` (the position of the block as submitted).
  function applyServerErrors(errors) {
    let first = null;
    const unmapped = [];
    const lines = lineEls();

    Object.entries(errors).forEach(([key, messages]) => {
      const message = messages[0];
      const parts = key.split('.');
      let target = null;

      if (parts[0] === 'services' && parts.length >= 3) {
        const line = lines[parseInt(parts[1], 10)];
        target = line ? fieldOf(line, parts[2]) : null;
      } else if (parts[0] === 'services' && parts.length === 2) {
        const line = lines[parseInt(parts[1], 10)];
        const box = line && line.querySelector('[data-line-feedback]');
        if (box) {
          box.textContent = message;
          box.classList.remove('d-none');
          first = first || line;
          return;
        }
      } else if (key === 'services') {
        linesFeedback.textContent = message;
        linesFeedback.classList.remove('d-none');
        first = first || linesFeedback;
        return;
      } else {
        target = form.querySelector(`[name="${key}"]`);
      }

      if (target) {
        showFieldError(target, message);
        first = first || target;
      } else {
        unmapped.push(message);
      }
    });

    if (unmapped.length) {
      errorBox.textContent = unmapped.join(' ');
      errorBox.classList.remove('d-none');
    }
    if (first && first.scrollIntoView) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else window.scrollTo(0, 0);
  }

  // ---------------------------------------------------------------- submit
  function buildBody() {
    const fd = new FormData();
    const put = (name, value) => fd.append(name, value == null ? '' : value);

    // With a trip on the PO the order's own fields are locked: leave them out entirely (the server compares what
    // is sent against what is stored, and Quill may re-write the notes' markup without changing their meaning).
    if (!locked) {
      put('paper_number', form.querySelector('#paper_number').value);
      put('company_id', companySelect.value);
      put('project_id', projectSelect.value);
      put('starts_on', form.querySelector('#starts_on').value);
      put('ends_on', form.querySelector('#ends_on').value);
      put('region', regionInput.value);
      put('additional_data', quill.getLength() > 1 ? quill.root.innerHTML : '');
      const file = form.querySelector('#attachment').files[0];
      if (file) fd.append('attachment', file);
    }
    if (mode === 'create') put('operational_status', (form.querySelector('input[name="operational_status"]:checked') || {}).value || 'active');

    lineEls().forEach((line, i) => {
      const base = `services[${i}]`;
      if (line.dataset.lineId) put(`${base}[id]`, line.dataset.lineId);
      // The identity fields are read from the controls, not the form: a locked (disabled) one still has to be sent.
      put(`${base}[main_service_id]`, fieldOf(line, 'main_service_id').value);
      put(`${base}[quota_method]`, methodOf(line));
      put(`${base}[max_trips]`, fieldOf(line, 'max_trips').value);
      put(`${base}[trip_cost]`, fieldOf(line, 'trip_cost').value);
      if (methodOf(line) === 'trips_and_ancillary') put(`${base}[ancillary_cost_per_trip]`, fieldOf(line, 'ancillary_cost_per_trip').value);
      put(`${base}[alert_percent]`, fieldOf(line, 'alert_percent').value);
    });

    if (mode === 'edit') fd.append('_method', 'PATCH');

    return fd;
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    const tooLarge = window.gcmFileGuard(form, { default: 5120 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      window.scrollTo(0, 0);
      return;
    }

    submitBtn.disabled = true;
    window.gcmBusy.start({ progress: true });

    const url = mode === 'edit' ? `/api/v1/purchase-orders/${id}` : '/api/v1/purchase-orders';

    window.axios
      .post(url, buildBody(), { headers: { Accept: 'application/json' }, onUploadProgress: window.gcmBusy.onUploadProgress })
      .then(() => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/po/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.list_url || '/app/po/list'}?created=1`;
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

  // ------------------------------------------------------ mode bootstrap
  function fillCompanies(selectedId) {
    companySelect.innerHTML = '<option value=""></option>';
    companies.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = `${c.name} (${c.code})`;
      companySelect.appendChild(opt);
    });
    companySelect.value = selectedId ? String(selectedId) : '';
    window.refreshGcmSelect(companySelect);
    setPrefix();
  }

  const loadLookups = (companyParams) =>
    Promise.all([
      window.gcmFetchAll('/api/v1/companies', Object.assign({ sort_by: 'name' }, companyParams)),
      window.gcmFetchAll('/api/v1/main-services', { operational_status: 'active', sort_by: 'name' })
    ]);

  const fail = (error) => {
    if (loading) loading.classList.add('d-none');
    errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
    errorBox.classList.remove('d-none');
  };

  function lock(editability) {
    locked = true;
    document.getElementById('po-lock-alert').classList.remove('d-none');
    ['#paper_number', '#starts_on', '#ends_on', '#region', '#attachment'].forEach((sel) => (form.querySelector(sel).disabled = true));
    [companySelect, projectSelect].forEach((s) => {
      s.disabled = true;
      window.refreshGcmSelect(s);
    });
    quill.enable(false);
    addLineBtn.classList.add('d-none');
    linesEmpty.classList.add('d-none');
    return editability;
  }

  function prefill(po) {
    form.querySelector('#paper_number').value = po.paper_number || '';
    form.querySelector('#starts_on').value = po.starts_on || '';
    form.querySelector('#ends_on').value = po.ends_on || '';
    regionInput.value = po.region || '';
    quill.root.innerHTML = po.additional_data || '';

    const stamp = document.getElementById('po-last-updated');
    if (stamp && po.updated_by_name && t.last_updated_by) {
      const at = po.updated_at ? new Date(po.updated_at).toLocaleString() : '';
      stamp.textContent = t.last_updated_by.replace(':name', po.updated_by_name).replace(':at', at);
    }

    existingAttachment = po.attachment;
    const holder = document.getElementById('po-current-attachment');
    if (existingAttachment && existingAttachment.download_url) {
      holder.textContent = '';
      const a = document.createElement('a');
      a.href = existingAttachment.download_url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.innerHTML = '<i class="ti ti-paperclip"></i> ';
      a.appendChild(document.createTextNode(t.current_attachment || 'Current attachment (choosing a file replaces it)'));
      holder.appendChild(a);
      holder.classList.remove('d-none');
    }
  }

  if (mode === 'create') {
    window.initGcmSelects(form);

    loadLookups({ operational_status: 'active' })
      .then(([list, services]) => {
        companies = list;
        mainServices = services;
        fillCompanies(null);

        const preselected = new URLSearchParams(window.location.search).get('company_id');
        if (preselected && companies.some((c) => String(c.id) === preselected)) {
          companySelect.value = preselected;
          window.refreshGcmSelect(companySelect);
          setPrefix();
          loadProjects(new URLSearchParams(window.location.search).get('project_id'));
        }
        addLine(null, null);
      })
      .catch(fail);
  }

  if (mode === 'edit') {
    const cancel = document.getElementById('po-form-cancel');
    if (cancel) {
      cancel.href = `${t.view_url_base || '/app/po/view'}/${id}`;
      if (t.cancel) cancel.textContent = t.cancel;
    }

    window.axios
      .get(`/api/v1/purchase-orders/${id}`)
      .then((res) => {
        const po = res.data.data;
        return loadLookups({}).then(([list, services]) => ({ po, list, services }));
      })
      .then(({ po, list, services }) => {
        companies = list;
        mainServices = services;
        window.initGcmSelects(form);
        prefill(po);
        fillCompanies(po.company && po.company.id);
        return loadProjects(po.project && po.project.id).then(() => {
          lastProjectRegion = ((projects.find((p) => String(p.id) === String(po.project && po.project.id)) || {}).operational_region) || '';
          const lockState = po.editability || {};
          const rules = new Map((lockState.services || []).map((s) => [s.id, s]));
          (po.services || []).forEach((line) => addLine(line, rules.get(line.id) || null));
          if (lockState.has_trips) lock(lockState);

          loading.classList.add('d-none');
          form.classList.remove('d-none');
          onLoaded(po);
        });
      })
      .catch(fail);
  }

  return { FIELDS };
}
