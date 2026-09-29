/**
 * Asset Capacity Category Details — client add-on beyond the FRD (§1.7.2
 * only specifies List/Create/Edit, with the list's detail link going
 * straight to edit — see AssetCategoryController's docblock). Mirrors
 * app-asset-view.js: fetch, render, and an Edit / Back to list pair, plus
 * a table of the assets carrying this capacity (GET /api/v1/assets
 * filtered by asset_capacity_category_id — see AssetController's
 * filteredQuery()). Read-only page — the category's own edit rules (name
 * only, capacity and applicability locked) still live in
 * app-asset-category-edit.js.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.assetCategoryViewTranslations || {};
  const id = window.assetCategoryViewId;
  const canManage = !!t.can_manage;

  const errorBox = document.getElementById('asset-category-view-error');
  const loading = document.getElementById('asset-category-view-loading');
  const content = document.getElementById('asset-category-view-content');

  const appliesLabels = {
    container: t.container || 'Containers',
    tank: t.tank || 'Tanks',
    both: t.both || 'All'
  };

  // Singular labels for the assets table (`t.container` / `t.tank` above
  // are the plural "Containers" / "Tanks" used for the category's "Applies
  // to" field).
  const typeLabels = { container: t.asset_type_container || 'Container', tank: t.asset_type_tank || 'Tank' };
  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    on_maintenance: { title: t.on_maintenance || 'On Maintenance', class: 'bg-label-warning' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  function escapeHtml(value) {
    const d = document.createElement('div');
    d.textContent = value == null ? '' : value;
    return d.innerHTML;
  }

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  function renderAssets(assets) {
    const loadingEl = document.getElementById('acv-assets-loading');
    const wrapper = document.getElementById('acv-assets-table-wrapper');
    const empty = document.getElementById('acv-assets-empty');
    const rows = document.getElementById('acv-assets-rows');

    loadingEl.classList.add('d-none');

    if (!assets.length) {
      empty.classList.remove('d-none');
      return;
    }

    rows.innerHTML = assets
      .map((a) => {
        const s = statusObj[a.operational_status] || { title: a.operational_status, class: 'bg-label-secondary' };
        const viewUrl = (t.asset_view_url_base || '/app/asset/view') + '/' + a.id;
        return (
          '<tr>' +
          '<td><span class="fw-medium">' + escapeHtml(a.name) + '</span></td>' +
          '<td>' + escapeHtml(typeLabels[a.asset_type] || a.asset_type) + '</td>' +
          '<td>' + (a.affiliation === 'gcm' ? escapeHtml(t.gcm || 'GCM') : escapeHtml(t.contractor || a.affiliation)) + '</td>' +
          '<td><span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span></td>' +
          '<td class="text-end">' +
          '<a href="' + viewUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
          (t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
          '</td>' +
          '</tr>'
        );
      })
      .join('');
    wrapper.classList.remove('d-none');
  }

  function loadAssets() {
    window.axios
      .get('/api/v1/assets', { params: { asset_capacity_category_id: id, per_page: 100, sort_by: 'name', sort_dir: 'asc' } })
      .then((response) => renderAssets(response.data.data))
      .catch(() => renderAssets([]));
  }

  function render(c) {
    document.getElementById('acv-name').textContent = c.name;
    document.getElementById('acv-applies-to').textContent = appliesLabels[c.applies_to] || c.applies_to;
    document.getElementById('acv-capacity-cbm').textContent = c.capacity_cbm;
    document.getElementById('acv-capacity-ton').textContent = c.capacity_ton;
    document.getElementById('acv-assets-count').textContent = c.assets_count ?? 0;

    if (c.additional_data) {
      document.getElementById('acv-additional').textContent = c.additional_data;
      document.getElementById('acv-additional-wrapper').classList.remove('d-none');
    }

    if (c.updated_by_name && t.last_updated_by) {
      const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
      document.getElementById('acv-updated-by').textContent = t.last_updated_by
        .replace(':name', c.updated_by_name)
        .replace(':at', at);
    }

    const editLink = document.getElementById('acv-edit-link');
    if (canManage) {
      editLink.href = (t.edit_url_base || '/app/asset-category/edit') + '/' + c.id;
    } else {
      editLink.remove();
    }

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/asset-capacity-categories/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  loadAssets();
});
