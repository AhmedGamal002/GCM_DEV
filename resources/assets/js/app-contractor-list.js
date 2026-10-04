/**
 * Contractors DataTable — same server-side setup as app-company-list.js,
 * wired to /api/v1/contractors. Columns follow the FRD V01.14 §1.10 list
 * spec: name / drivers / vehicles / containers (+ tanks) / status / details,
 * with the contractor ID alongside. Also fills the four statistic cards from
 * /api/v1/contractors/stats. Write buttons (Add, Edit) are for system admin /
 * data entry only — the Blade shell passes `can_manage`, so the auditor sees
 * view and export only.
 */
'use strict';

$(function () {
  const t = window.contractorListTranslations || {};
  const dt = $('.datatables-contractors');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('contractor-list-status');
    box.textContent = t.created || 'Contractor created successfully.';
    box.classList.remove('d-none');
  }

  // Stat cards — contractors, and the vehicles / assets / drivers they supply.
  window.axios
    .get('/api/v1/contractors/stats')
    .then(function (response) {
      const flat = {};
      Object.entries(response.data.data || {}).forEach(([key, val]) => {
        if (val !== null && typeof val === 'object') {
          Object.entries(val).forEach(([sub, n]) => (flat[`${key}.${sub}`] = n));
        } else {
          flat[key] = val;
        }
      });
      // "assets" card shows the container + tank total as its big number.
      flat.assets = (flat['assets.container'] || 0) + (flat['assets.tank'] || 0);

      Object.entries(flat).forEach(([key, val]) => {
        const el = document.querySelector(`[data-stat="${key}"]`);
        if (el) el.textContent = val;
      });
    })
    .catch(() => {});

  if (!dt.length) {
    return;
  }

  const canManage = !!t.can_manage;
  let currentStatus = '';

  // Filters currently applied to the list — the table's own requests AND the
  // Excel/PDF export read this, so an export always matches what is on screen.
  const listParams = () => ({
    operational_status: currentStatus || undefined
  });

  const buttons = [
    {
      extend: 'collection',
      className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
      text: '<i class="ti ti-upload me-2 ti-xs"></i>' + (t.export || 'Export'),
      buttons: [
        {
          text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/contractors/export', 'xlsx', dt, listParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/contractors/export', 'pdf', dt, listParams)
        }
      ]
    }
  ];

  if (canManage) {
    buttons.push({
      text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_contractor || 'Add Contractor') + '</span>',
      className: 'add-new btn btn-primary waves-effect waves-light',
      action: () => window.location.assign(t.add_contractor_url || '/app/contractor/add')
    });
  }

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/contractors', listParams, t.no_permission),
    columns: [
      { data: 'id' },
      { data: 'code' },
      { data: 'name' },
      { data: 'drivers_count' },
      { data: 'vehicles_count' },
      { data: 'containers_count' },
      { data: 'tanks_count' },
      { data: 'operational_status' },
      { data: 'id' }
    ],
    columnDefs: [
      {
        className: 'control',
        searchable: false,
        orderable: false,
        responsivePriority: 2,
        targets: 0,
        render: () => ''
      },
      {
        targets: 1,
        render: (data, type, full) => '<span dir="ltr">' + escapeHtml(full.code) + '</span>'
      },
      {
        targets: 2,
        responsivePriority: 4,
        // The trading name is shown (and searchable) under the name.
        render: (data, type, full) =>
          '<div class="d-flex flex-column"><span class="fw-medium">' + escapeHtml(full.name) + '</span>' +
          (full.trading_name ? '<small class="text-muted">' + escapeHtml(full.trading_name) + '</small>' : '') + '</div>'
      },
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.drivers_count) },
      { targets: 4, orderable: false, render: (data, type, full) => escapeHtml(full.vehicles_count) },
      { targets: 5, orderable: false, render: (data, type, full) => escapeHtml(full.containers_count) },
      { targets: 6, orderable: false, render: (data, type, full) => escapeHtml(full.tanks_count) },
      {
        targets: 7,
        render: function (data, type, full) {
          if (type !== 'display') return full.operational_status;
          const s = statusObj[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const viewUrl = (t.view_url_base || '/app/contractor/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/contractor/edit') + '/' + full.id;
          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + viewUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            (t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
            (canManage
              ? '<a href="' + editUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
                (t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>'
              : '') +
            '</div>'
          );
        }
      }
    ],
    order: [[2, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_contractor || 'Search Contractor',
      emptyTable: t.no_contractors_found || 'No contractors found.',
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
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => (t.view || 'Details') + ' — ' + row.data().name
        }),
        type: 'column',
        renderer: function (api, rowIdx, columns) {
          const data = $.map(columns, (col) =>
            col.title !== ''
              ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                '<td>' + col.title + ':</td><td>' + col.data + '</td></tr>'
              : ''
          ).join('');
          return data ? $('<table class="table"/><tbody />').append(data) : false;
        }
      }
    },
    initComplete: function () {
      const api = this.api();

      const statusSelect = $(
        '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
      )
        .appendTo('.contractor_status')
        .on('change', function () {
          currentStatus = $(this).val();
          api.draw();
        });
      ['active', 'deactivated'].forEach((status) => {
        statusSelect.append('<option value="' + status + '">' + statusObj[status].title + '</option>');
      });
    }
  });
});
