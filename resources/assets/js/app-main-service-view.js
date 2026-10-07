/**
 * Main Service Details — FRD V01.14 §1.9.1: name / type / status, the
 * supported vehicle categories, the (currently zero) statistics and a
 * server-side DataTable of its sub-services (GET /api/v1/sub-services
 * ?main_service_id=…). Status changes happen on the edit page.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.mainServiceViewTranslations || {};
  const id = window.mainServiceViewId;

  const errorBox = document.getElementById('main-service-view-error');
  const loading = document.getElementById('main-service-view-loading');
  const content = document.getElementById('main-service-view-content');

  const query = new URLSearchParams(window.location.search);
  const flash = query.get('saved') ? t.saved : query.get('created') ? t.created : null;
  if (flash) {
    const box = document.getElementById('main-service-view-status');
    box.textContent = flash;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const typeLabels = { non_hazardous: t.non_hazardous, hazardous: t.hazardous, supplying: t.supplying };
  const assetLabels = { container: t.container || 'Container', tank: t.tank || 'Tank' };

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  function render(s) {
    document.getElementById('msv-name').textContent = s.name;
    document.getElementById('msv-type').textContent = typeLabels[s.service_type] || s.service_type;
    document.getElementById('msv-sub-count').textContent = s.sub_services_count != null ? s.sub_services_count : (s.sub_services || []).length;

    document.getElementById('msv-categories').innerHTML = (s.vehicle_categories || [])
      .map((c) => '<span class="badge bg-label-primary">' + escapeHtml(c.name) + '</span>')
      .join('');

    if (s.additional_data) {
      document.getElementById('msv-additional').innerHTML = s.additional_data;
      document.getElementById('msv-additional-wrapper').classList.remove('d-none');
    }

    if (s.updated_by_name && t.last_updated_by) {
      const at = s.updated_at ? new Date(s.updated_at).toLocaleString() : '';
      document.getElementById('msv-updated-by').textContent = t.last_updated_by.replace(':name', s.updated_by_name).replace(':at', at);
    }

    const badge = document.getElementById('msv-status');
    const st = statusObj[s.operational_status] || { title: s.operational_status, class: 'bg-label-secondary' };
    badge.textContent = st.title;
    badge.className = 'badge ' + st.class;

    Object.entries(s.stats || {}).forEach(([key, val]) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = val;
    });

    const editLink = document.getElementById('msv-edit-link');
    if (editLink) editLink.href = (t.edit_url_base || '/app/main-service/edit') + '/' + s.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/main-services/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => {
      loading.classList.add('d-none');
      errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
      errorBox.classList.remove('d-none');
    });

  // ------------------------------------------- sub-services under this one
  const dt = $('.datatables-main-service-subs');
  if (!dt.length) return;

  const subView = t.sub_view_url_base || '/app/sub-service/view';
  const subEdit = t.sub_edit_url_base || '/app/sub-service/edit';
  // Filters of the sub-services table (asset type + status), read by every request.
  let currentAssetType = '';
  let currentSubStatus = '';
  const listParams = () => ({
    main_service_id: id,
    asset_type: currentAssetType || undefined,
    operational_status: currentSubStatus || undefined
  });

  // Excel / PDF of exactly the rows the table shows (same filters + search).
  const buttons = [
    {
      extend: 'collection',
      className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
      text: '<i class="ti ti-upload me-2 ti-xs"></i>' + escapeHtml(t.export || 'Export'),
      buttons: [
        {
          text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/sub-services/export', 'xlsx', dt, listParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/sub-services/export', 'pdf', dt, listParams)
        }
      ]
    }
  ];
  if (t.can_manage) {
    buttons.push({
      text:
        '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' +
        escapeHtml(t.add_sub_service || 'Create New Sub-service') +
        '</span>',
      className: 'btn btn-primary waves-effect waves-light ms-4',
      action: () => window.location.assign((t.add_sub_url || '/app/sub-service/add') + '?main_service_id=' + id)
    });
  }

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/sub-services', listParams, t.no_permission),
    columns: [{ data: 'name' }, { data: 'asset_type' }, { data: 'operational_status' }, { data: 'id' }],
    columnDefs: [
      {
        targets: 0,
        responsivePriority: 1,
        render: (data, type, full) => '<a href="' + subView + '/' + full.id + '" class="fw-medium">' + escapeHtml(full.name) + '</a>'
      },
      {
        targets: 1,
        render: (data, type, full) => (type === 'display' ? escapeHtml(assetLabels[full.asset_type] || full.asset_type) : full.asset_type)
      },
      {
        targets: 2,
        render: function (data, type, full) {
          const s = statusObj[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: (data, type, full) =>
          '<div class="d-flex align-items-center">' +
          '<a href="' + subView + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
          escapeHtml(t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
          (t.can_manage
            ? '<a href="' + subEdit + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
              escapeHtml(t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>'
            : '') +
          '</div>'
      }
    ],
    order: [[0, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_sub_service || 'Search sub-service',
      emptyTable: t.no_sub_services_found || 'No sub-services under this main service yet.',
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
      const addFilter = (selector, allLabel, options, onChange) => {
        const select = $('<select class="form-select"><option value="">' + escapeHtml(allLabel) + '</option></select>')
          .appendTo(selector)
          .on('change', function () {
            onChange($(this).val());
            api.draw();
          });
        options.forEach(([value, label]) => select.append('<option value="' + value + '">' + escapeHtml(label) + '</option>'));
      };

      addFilter('.msv_sub_asset_type', t.all_asset_types || 'All asset types', [['container', assetLabels.container], ['tank', assetLabels.tank]], (v) => (currentAssetType = v));
      addFilter('.msv_sub_status', t.all_statuses || 'All statuses', [['active', statusObj.active.title], ['deactivated', statusObj.deactivated.title]], (v) => (currentSubStatus = v));
    }
  });
});
