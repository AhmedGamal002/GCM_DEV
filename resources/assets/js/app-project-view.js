/**
 * Project Details — mirrors app-company-view.js. Renders the project, its
 * (currently zero) statistics and a server-side DataTable of the assets
 * inserted into it (GET /api/v1/assets?project_id=…, type filter,
 * Excel/PDF export of exactly what is shown). Status changes happen on the
 * edit page (FRD V01.14 §1.12.4). The contracts section is a placeholder
 * until the Contracts module exists.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.projectViewTranslations || {};
  const id = window.projectViewId;

  const errorBox = document.getElementById('project-view-error');
  const loading = document.getElementById('project-view-loading');
  const content = document.getElementById('project-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('project-view-status');
    box.textContent = t.saved;
    box.classList.remove('d-none');
  }
  if (query.get('asset_inserted')) {
    const box = document.getElementById('project-view-status');
    box.textContent = t.asset_inserted;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  const text = (elId, value) => {
    document.getElementById(elId).textContent = value == null || value === '' ? '—' : value;
  };

  function render(p) {
    text('pv-name', p.name);
    text('pv-code', p.code);
    text('pv-region', p.operational_region);
    text('pv-phone', p.phone);
    text('pv-email', p.email);
    text('pv-address', p.address);

    // The company name is user input — build the anchor with DOM APIs.
    if (p.company) {
      const holder = document.getElementById('pv-company');
      holder.textContent = '';
      const a = document.createElement('a');
      a.href = (t.company_view_url_base || '/app/company/view') + '/' + p.company.id;
      a.textContent = p.company.name;
      holder.appendChild(a);
    }

    // The map link is user input too.
    if (p.location_url) {
      const holder = document.getElementById('pv-location');
      holder.textContent = '';
      const a = document.createElement('a');
      a.href = p.location_url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = p.location_url;
      holder.appendChild(a);
    }

    if (p.additional_data) {
      document.getElementById('pv-additional').innerHTML = p.additional_data;
      document.getElementById('pv-additional-wrapper').classList.remove('d-none');
    }

    if (p.updated_by_name && t.last_updated_by) {
      const at = p.updated_at ? new Date(p.updated_at).toLocaleString() : '';
      document.getElementById('pv-updated-by').textContent = t.last_updated_by
        .replace(':name', p.updated_by_name)
        .replace(':at', at);
    }

    const badge = document.getElementById('pv-status');
    const s = statusObj[p.operational_status] || { title: p.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    Object.entries(p.stats || {}).forEach(([key, val]) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = val;
    });

    const editLink = document.getElementById('pv-edit-link');
    if (editLink) editLink.href = (t.edit_url_base || '/app/project/edit') + '/' + p.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/projects/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  // ------------------------------------------------- assets in this project
  const dt = $('.datatables-project-assets');
  if (!dt.length) return;

  const assetStatus = {
    active: { title: t.active, class: 'bg-label-success' },
    in_project: { title: t.in_project, class: 'bg-label-info' },
    on_maintenance: { title: t.on_maintenance, class: 'bg-label-warning' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const typeLabels = { container: t.container || 'Container', tank: t.tank || 'Tank' };

  let currentType = '';
  const listParams = () => ({ project_id: id, asset_type: currentType || undefined });

  const buttons = [
    {
      extend: 'collection',
      className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
      text: '<i class="ti ti-upload me-2 ti-xs"></i>' + (t.export || 'Export'),
      buttons: [
        {
          text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/assets/export', 'xlsx', dt, listParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/assets/export', 'pdf', dt, listParams)
        }
      ]
    }
  ];

  if (t.can_insert_asset) {
    buttons.push({
      text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.insert_asset || 'Insert asset into project') + '</span>',
      className: 'btn btn-primary waves-effect waves-light',
      action: () => window.location.assign((t.insert_asset_url || '/app/asset/insert-into-project') + '?project_id=' + id)
    });
  }

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/assets', listParams, t.no_permission),
    columns: [
      { data: 'name' },
      { data: 'capacity_category' },
      { data: 'affiliation' },
      { data: 'asset_type' },
      { data: 'availability' },
      { data: 'id' }
    ],
    columnDefs: [
      {
        targets: 0,
        responsivePriority: 1,
        render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>'
      },
      {
        targets: 1,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.capacity_category && full.capacity_category.name)
      },
      {
        targets: 2,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.affiliation === 'gcm' ? t.gcm || 'GCM' : t.contractor || full.affiliation)
      },
      {
        targets: 3,
        render: (data, type, full) => (type === 'display' ? escapeHtml(typeLabels[full.asset_type] || full.asset_type) : full.asset_type)
      },
      {
        targets: 4,
        orderable: false,
        render: function (data, type, full) {
          const s = assetStatus[full.availability] || { title: full.availability, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: (data, type, full) =>
          '<a href="' + (t.asset_view_url_base || '/app/asset/view') + '/' + full.id +
          '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' + (t.view || 'View') +
          '"><i class="ti ti-eye ti-md"></i></a>'
      }
    ],
    order: [[0, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_asset || 'Search Asset',
      emptyTable: t.no_assets_found || 'No assets are in this project.',
      info: t.info || 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: t.info_empty || 'Showing 0 to 0 of 0 entries',
      paginate: {
        next: '<i class="ti ti-chevron-right ti-sm"></i>',
        previous: '<i class="ti ti-chevron-left ti-sm"></i>'
      }
    },
    dom:
      '<"row"' +
      '<"col-md-2"<"ms-n2"l>>' +
      '<"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"fB>>' +
      '>t' +
      '<"row"' +
      '<"col-sm-12 col-md-6"i>' +
      '<"col-sm-12 col-md-6"p>' +
      '>',
    buttons: buttons,
    responsive: true,
    initComplete: function () {
      const api = this.api();

      const typeSelect = $(
        '<select class="form-select"><option value="">' + (t.all_types || 'All types') + '</option></select>'
      )
        .appendTo('.project_asset_type')
        .on('change', function () {
          currentType = $(this).val();
          api.draw();
        });
      ['container', 'tank'].forEach((type) => {
        typeSelect.append('<option value="' + type + '">' + (typeLabels[type] || type) + '</option>');
      });
    }
  });
});
