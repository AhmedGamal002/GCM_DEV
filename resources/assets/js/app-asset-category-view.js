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

  // Real (server-side, searchable, paginated) DataTable — same shell as
  // the Assets list page, filtered to just this category's assets so the
  // count here is never capped like a plain single fetch would be.
  function initAssetsTable() {
    $('.datatables-category-assets').DataTable({
      processing: true,
      serverSide: true,
      searchDelay: 500,
      ajax: window.gcmServerSideAjax('/api/v1/assets', () => ({ asset_capacity_category_id: id }), t.no_permission),
      columns: [{ data: 'name' }, { data: 'asset_type' }, { data: 'affiliation' }, { data: 'operational_status' }, { data: 'id' }],
      columnDefs: [
        {
          targets: 0,
          render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>'
        },
        {
          targets: 1,
          render: (data, type, full) => (type === 'display' ? escapeHtml(typeLabels[full.asset_type] || full.asset_type) : full.asset_type)
        },
        {
          targets: 2,
          orderable: false,
          render: (data, type, full) => (full.affiliation === 'gcm' ? escapeHtml(t.gcm || 'GCM') : escapeHtml(t.contractor || full.affiliation))
        },
        {
          targets: 3,
          render: function (data, type, full) {
            if (type !== 'display') return full.operational_status;
            const s = statusObj[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
            return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
          }
        },
        {
          targets: -1,
          orderable: false,
          searchable: false,
          className: 'text-end',
          render: function (data, type, full) {
            const viewUrl = (t.asset_view_url_base || '/app/asset/view') + '/' + full.id;
            return (
              '<a href="' + viewUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
              (t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>'
            );
          }
        }
      ],
      order: [[0, 'asc']],
      language: {
        search: '',
        searchPlaceholder: t.search_asset || 'Search Asset',
        emptyTable: t.no_assets_found || 'No assets found.',
        info: t.info || 'Showing _START_ to _END_ of _TOTAL_ entries',
        infoEmpty: t.info_empty || 'Showing 0 to 0 of 0 entries',
        paginate: {
          next: '<i class="ti ti-chevron-right ti-sm"></i>',
          previous: '<i class="ti ti-chevron-left ti-sm"></i>'
        }
      },
      dom: '<"row"<"col-12"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      responsive: true
    });
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

  initAssetsTable();
});
