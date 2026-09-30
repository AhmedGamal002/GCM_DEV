/**
 * Client Company Details — mirrors app-asset-view.js. Renders the company,
 * its contract / registration data with authorised download links for the
 * private attachments, and the (currently zero) statistics. Status changes
 * happen on the edit page (FRD V01.14 §1.11.4).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.companyViewTranslations || {};
  const id = window.companyViewId;

  const errorBox = document.getElementById('company-view-error');
  const loading = document.getElementById('company-view-loading');
  const content = document.getElementById('company-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('company-view-status');
    box.textContent = t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  const text = (elId, value) => {
    document.getElementById(elId).textContent = value == null || value === '' ? '—' : value;
  };

  function fileLink(elId, url) {
    const el = document.getElementById(elId);
    el.innerHTML = '';
    if (!url) {
      if (elId === 'cv-contract-file') el.textContent = '—';
      return;
    }
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.innerHTML = '<i class="ti ti-download"></i> ';
    link.appendChild(document.createTextNode(t.download || 'Download'));
    el.appendChild(link);
  }

  function render(c) {
    text('cv-name', c.name);
    text('cv-code', c.code);
    text('cv-prefix', c.prefix);
    text('cv-sector', c.business_sector);
    text('cv-phone', c.phone);
    text('cv-email', c.email);
    text('cv-address', c.address);

    if (c.logo_url) {
      const img = document.getElementById('cv-logo');
      img.src = c.logo_url;
      img.classList.remove('d-none');
      document.getElementById('cv-logo-fallback').classList.add('d-none');
    }

    // The map link is user input — build the anchor with DOM APIs, never innerHTML.
    if (c.location_url) {
      const holder = document.getElementById('cv-location');
      holder.textContent = '';
      const a = document.createElement('a');
      a.href = c.location_url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = c.location_url;
      holder.appendChild(a);
    }

    text('cv-contract-number', c.contract && c.contract.number);
    const from = c.contract && c.contract.start_date;
    const to = c.contract && c.contract.end_date;
    text('cv-contract-period', from || to ? (t.period || ':from → :to').replace(':from', from || '…').replace(':to', to || '…') : null);
    fileLink('cv-contract-file', c.contract && c.contract.download_url);

    text('cv-cr-number', c.commercial_registration && c.commercial_registration.number);
    fileLink('cv-cr-file', c.commercial_registration && c.commercial_registration.download_url);
    text('cv-tax-number', c.tax_registration && c.tax_registration.number);
    fileLink('cv-tax-file', c.tax_registration && c.tax_registration.download_url);

    if (c.additional_data) {
      document.getElementById('cv-additional').innerHTML = c.additional_data;
      document.getElementById('cv-additional-wrapper').classList.remove('d-none');
    }

    if (c.updated_by_name && t.last_updated_by) {
      const at = c.updated_at ? new Date(c.updated_at).toLocaleString() : '';
      document.getElementById('cv-updated-by').textContent = t.last_updated_by
        .replace(':name', c.updated_by_name)
        .replace(':at', at);
    }

    const badge = document.getElementById('cv-status');
    const s = statusObj[c.operational_status] || { title: c.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    Object.entries(c.stats || {}).forEach(([key, val]) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = val;
    });

    document.getElementById('cv-edit-link').href = (t.edit_url_base || '/app/company/edit') + '/' + c.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/companies/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  // ------------------------------------------------------ company projects
  // Server-side DataTable of this company's projects (FRD V01.14 §1.11.3):
  // status filter, search, and Excel/PDF export of exactly what is shown.
  const projectsTable = $('.datatables-company-projects');
  if (!projectsTable.length) return;

  const projectStatus = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();

  let currentProjectStatus = '';
  const projectParams = () => ({ company_id: id, operational_status: currentProjectStatus || undefined });

  const buttons = [
    {
      extend: 'collection',
      className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
      text: '<i class="ti ti-upload me-2 ti-xs"></i>' + (t.export || 'Export'),
      buttons: [
        {
          text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/projects/export', 'xlsx', dt, projectParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/projects/export', 'pdf', dt, projectParams)
        }
      ]
    }
  ];

  if (t.can_manage) {
    buttons.push({
      text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_project || 'Add Project') + '</span>',
      className: 'btn btn-primary waves-effect waves-light',
      action: () => window.location.assign((t.add_project_url || '/app/project/add') + '?company_id=' + id)
    });
  }

  projectsTable.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/projects', projectParams, t.no_permission),
    columns: [
      { data: 'code' },
      { data: 'name' },
      { data: 'contracts_count' },
      { data: 'users_count' },
      { data: 'operational_status' },
      { data: 'id' }
    ],
    columnDefs: [
      { targets: 0, render: (data, type, full) => escapeHtml(full.code) },
      { targets: 1, responsivePriority: 1, render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>' },
      { targets: 2, orderable: false, render: (data, type, full) => escapeHtml(full.contracts_count) },
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.users_count) },
      {
        targets: 4,
        render: function (data, type, full) {
          if (type !== 'display') return full.operational_status;
          const s = projectStatus[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: (data, type, full) =>
          '<a href="' + (t.project_view_url_base || '/app/project/view') + '/' + full.id +
          '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' + (t.view || 'View') +
          '"><i class="ti ti-eye ti-md"></i></a>'
      }
    ],
    order: [[1, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_project || 'Search Project',
      emptyTable: t.no_projects_found || 'This company has no projects yet.',
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
      const select = $('<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>')
        .appendTo('.company_project_status')
        .on('change', function () {
          currentProjectStatus = $(this).val();
          api.draw();
        });
      ['active', 'deactivated'].forEach((status) => {
        select.append('<option value="' + status + '">' + projectStatus[status].title + '</option>');
      });
    }
  });
});
