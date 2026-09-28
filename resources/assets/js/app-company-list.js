/**
 * Client Companies DataTable — same server-side setup as app-asset-list.js,
 * wired to /api/v1/companies. Columns match the FRD V01.14 §1.11 list spec:
 * ID / Company / Representative / Projects / Users / Status / Actions.
 * Representative, Projects and Users are placeholders until the client
 * accounts and Projects modules exist (the API reports null / 0).
 */
'use strict';

$(function () {
  const t = window.companyListTranslations || {};
  const dt = $('.datatables-companies');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('company-list-status');
    box.textContent = t.created || 'Client company created successfully.';
    box.classList.remove('d-none');
  }

  if (!dt.length) {
    return;
  }

  let currentStatus = '';

  // Filters currently applied to the list — the table's own requests AND the
  // Excel/PDF export read this, so an export always matches what is on screen.
  const listParams = () => ({
    operational_status: currentStatus || undefined
  });

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/companies', listParams, t.no_permission),
    columns: [
      { data: 'id' },
      { data: 'id' },
      { data: 'code' },
      { data: 'name' },
      { data: 'representative' },
      { data: 'projects_count' },
      { data: 'users_count' },
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
        orderable: false,
        searchable: false,
        checkboxes: { selectAllRender: '<input type="checkbox" class="form-check-input">' },
        render: () => '<input type="checkbox" class="dt-checkboxes form-check-input">'
      },
      {
        targets: 2,
        render: (data, type, full) => escapeHtml(full.code)
      },
      {
        targets: 3,
        responsivePriority: 4,
        render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>'
      },
      {
        targets: 4,
        orderable: false,
        render: (data, type, full) => (full.representative ? escapeHtml(full.representative.name) : '—')
      },
      {
        targets: 5,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.projects_count)
      },
      {
        targets: 6,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.users_count)
      },
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
          const viewUrl = (t.view_url_base || '/app/company/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/company/edit') + '/' + full.id;
          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + viewUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            (t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
            '<a href="' + editUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            (t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>' +
            '</div>'
          );
        }
      }
    ],
    order: [[3, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_company || 'Search Client Company',
      emptyTable: t.no_companies_found || 'No client companies found.',
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
    buttons: [
      {
        extend: 'collection',
        className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
        text: '<i class="ti ti-upload me-2 ti-xs"></i>' + (t.export || 'Export'),
        buttons: [
          {
            text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/companies/export', 'xlsx', dt, listParams)
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/companies/export', 'pdf', dt, listParams)
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_company || 'Add Client Company') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_company_url || '/app/company/add')
      }
    ],
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
        .appendTo('.company_status')
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
