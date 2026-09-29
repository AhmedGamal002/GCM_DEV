/**
 * Insert an asset into a project (FRD V01.14 §1.7.3): pick a client
 * company → one of its ACTIVE projects → the asset type → an available
 * asset of that type (active and not already in a project — the same
 * `operational_status=active` availability filter the assets list uses).
 * Submits to POST /api/v1/projects/{project}/assets. The company / type
 * pickers only narrow the lists; the server re-checks that the project is
 * active and the asset is still available.
 *
 * `?project_id=` (from a project's page) preselects the company + project.
 * Every list is loaded page by page (gcmFetchAll) so nothing past a fixed
 * page size goes missing from the pickers.
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.assetInsertTranslations || {};
  const form = document.getElementById('insertAssetForm');
  if (!form) return;

  const errorBox = document.getElementById('insert-form-error');
  const companySelect = document.getElementById('company_id');
  const projectSelect = document.getElementById('project_id');
  const assetSelect = document.getElementById('asset_id');
  const assetHint = document.getElementById('asset-hint');
  const submitBtn = form.querySelector('button[type="submit"]');
  const $ = window.$;

  window.initGcmSelects(form);

  // ---------------------------------------------------------------- errors
  const feedbackEl = (el) => el.closest('[class*="col-"]').querySelector('[data-feedback]');

  function showFieldError(el, msg) {
    el.classList.add('is-invalid');
    const fb = feedbackEl(el);
    fb.textContent = msg;
    fb.classList.remove('d-none');
  }

  function clearFieldError(el) {
    el.classList.remove('is-invalid');
    const fb = feedbackEl(el);
    fb.textContent = '';
    fb.classList.add('d-none');
  }

  function showFormError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('d-none');
    window.scrollTo(0, 0);
  }

  [companySelect, projectSelect, assetSelect].forEach((el) => $(el).on('change', () => clearFieldError(el)));

  // ------------------------------------------------------------- pickers
  function fill(select, rows, placeholder, label) {
    select.innerHTML = '<option value=""></option>';
    rows.forEach((row) => {
      const opt = document.createElement('option');
      opt.value = row.id;
      opt.textContent = label(row);
      select.appendChild(opt);
    });
    select.dataset.placeholder = placeholder;
    select.disabled = rows.length === 0;
    window.refreshGcmSelect(select);
  }

  function loadProjects(companyId, selectedId) {
    if (!companyId) {
      fill(projectSelect, [], t.select_project, () => '');
      return Promise.resolve();
    }
    return window
      .gcmFetchAll('/api/v1/projects', { company_id: companyId, operational_status: 'active', sort_by: 'name' })
      .then((projects) => {
        fill(projectSelect, projects, projects.length ? t.select_project : t.no_projects, (p) => `${p.name} (${p.code})`);
        if (selectedId && projects.some((p) => String(p.id) === String(selectedId))) {
          projectSelect.value = selectedId;
          window.refreshGcmSelect(projectSelect);
        }
      });
  }

  // Available = active and still in the pool; a stale list is harmless
  // (the server re-checks) but reloading on type change keeps it honest.
  function loadAssets() {
    const type = form.querySelector('input[name="asset_type"]:checked').value;
    return window
      .gcmFetchAll('/api/v1/assets', { asset_type: type, operational_status: 'active', sort_by: 'name' })
      .then((assets) => {
        fill(assetSelect, assets, assets.length ? t.select_asset : t.no_assets, (a) => a.name);
        assetHint.textContent = assets.length ? '' : t.no_assets;
      });
  }

  // company → its projects
  $(companySelect).on('change', () => {
    fill(projectSelect, [], t.select_project, () => '');
    loadProjects(companySelect.value).catch(() => showFormError(t.generic_error));
  });

  form.querySelectorAll('input[name="asset_type"]').forEach((radio) => {
    radio.addEventListener('change', () => loadAssets().catch(() => showFormError(t.generic_error)));
  });

  // --------------------------------------------------------- initial load
  const preselectedProject = new URLSearchParams(window.location.search).get('project_id');

  const companiesLoaded = window.gcmFetchAll('/api/v1/companies', { operational_status: 'active', sort_by: 'name' }).then((companies) => {
    fill(companySelect, companies, t.select_company, (c) => `${c.name} (${c.code})`);
    return companies;
  });

  Promise.all([companiesLoaded, loadAssets()])
    .then(([companies]) => {
      if (!preselectedProject) return null;
      // Preselect via the project's own company.
      return window.axios.get(`/api/v1/projects/${preselectedProject}`).then((res) => {
        const project = res.data.data;
        if (project.operational_status !== 'active' || !companies.some((c) => c.id === project.company.id)) return null;
        companySelect.value = project.company.id;
        window.refreshGcmSelect(companySelect);
        return loadProjects(project.company.id, project.id);
      });
    })
    .catch(() => showFormError(t.generic_error));

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    errorBox.classList.add('d-none');

    let firstInvalid = null;
    [companySelect, projectSelect, assetSelect].forEach((el) => {
      clearFieldError(el);
      if (!el.value) {
        showFieldError(el, t.required);
        if (!firstInvalid) firstInvalid = el;
      }
    });
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    submitBtn.disabled = true;
    window.gcmBusy.start();

    window.axios
      .post(`/api/v1/projects/${projectSelect.value}/assets`, { asset_id: assetSelect.value })
      .then(() => {
        window.location.href = `${t.project_view_url_base}/${projectSelect.value}?asset_inserted=1`;
      })
      .catch((error) => {
        window.gcmBusy.stop();
        submitBtn.disabled = false;
        const res = error.response;
        if (res && res.status === 422 && res.data && res.data.errors) {
          const map = { project: projectSelect, asset_id: assetSelect };
          const unmapped = [];
          Object.entries(res.data.errors).forEach(([key, messages]) => {
            if (map[key]) showFieldError(map[key], messages[0]);
            else unmapped.push(messages[0]);
          });
          if (unmapped.length) showFormError(unmapped.join(' '));
          // The list may be stale (someone else took the asset) — refresh it.
          loadAssets().catch(() => {});
        } else {
          showFormError((res && res.data && res.data.message) || t.generic_error);
        }
      });
  });
});
