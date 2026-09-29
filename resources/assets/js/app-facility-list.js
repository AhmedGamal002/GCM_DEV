/**
 * Intermediate Facilities DataTable — the same server-side setup as
 * app-asset-list.js, wired to /api/v1/facilities. Columns match the FRD §1.8
 * list spec: Name / Environmental service / Status / Details. The "Details"
 * column links to the dedicated details page — editing and status changes
 * happen there.
 */
'use strict';

$(function () {
  const t = window.facilityListTranslations || {};
  const dt = $('.datatables-facilities');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  const serviceLabels = {
    disposal: t.disposal || 'Safe disposal',
    sewage_treatment: t.sewage_treatment || 'Sewage treatment',
    recycle: t.recycle || 'Recycling'
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('facility-list-status');
    box.textContent = t.created || 'Facility created successfully.';
    box.classList.remove('d-none');
  }

  // Stat cards — how many facilities offer each environmental service.
  window.axios
    .get('/api/v1/facilities/stats')
    .then(function (response) {
      Object.entries(response.data.data || {}).forEach(([service, counts]) => {
        Object.entries(counts).forEach(([key, val]) => {
          const el = document.querySelector(`[data-stat="${service}.${key}"]`);
          if (el) el.textContent = val;
        });
      });
    })
    .catch(() => {});

  if (!dt.length) {
    return;
  }

  const canManage = !!t.can_manage;
  let currentService = '';
  let currentStatus = '';

  // Filters currently applied to the list — the table's own requests AND the
  // Excel/PDF export read this, so an export always matches what is on screen.
  const listParams = () => ({
    environmental_service: currentService || undefined,
    operational_status: currentStatus || undefined
  });

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/facilities', listParams),
    columns: [
      { data: 'id' },
      { data: 'name' },
      { data: 'environmental_service' },
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
        responsivePriority: 4,
        // The prefix is shown (and searchable) under the name.
        render: (data, type, full) =>
          '<div class="d-flex flex-column"><span class="fw-medium">' + escapeHtml(full.name) + '</span>' +
          '<small class="text-muted" dir="ltr">' + escapeHtml(full.prefix) + '</small></div>'
      },
      {
        targets: 2,
        render: function (data, type, full) {
          if (type !== 'display') return full.environmental_service;
          let label = escapeHtml(serviceLabels[full.environmental_service] || full.environmental_service);
          if (full.environmental_service === 'recycle' && full.recycling_efficiency !== null) {
            label += ' <small class="text-muted">(' + escapeHtml(full.recycling_efficiency) + '%)</small>';
          }
          return label;
        }
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
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const viewUrl = (t.view_url_base || '/app/facility/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/facility/edit') + '/' + full.id;
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
    order: [[1, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_facility || 'Search facility',
      emptyTable: t.no_facilities_found || 'No facilities found.',
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
            action: (e, dt) => window.gcmExport('/api/v1/facilities/export', 'xlsx', dt, listParams)
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/facilities/export', 'pdf', dt, listParams)
          }
        ]
      },
      ...(canManage
        ? [
            {
              text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_facility || 'Create new facility') + '</span>',
              className: 'add-new btn btn-primary waves-effect waves-light',
              action: () => window.location.assign(t.add_facility_url || '/app/facility/add')
            }
          ]
        : [])
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

      const serviceSelect = $(
        '<select class="form-select"><option value="">' + (t.all_services || 'All services') + '</option></select>'
      )
        .appendTo('.facility_service')
        .on('change', function () {
          currentService = $(this).val();
          api.draw();
        });
      Object.entries(serviceLabels).forEach(([value, label]) => {
        serviceSelect.append('<option value="' + value + '">' + escapeHtml(label) + '</option>');
      });

      const statusSelect = $(
        '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
      )
        .appendTo('.facility_status')
        .on('change', function () {
          currentStatus = $(this).val();
          api.draw();
        });
      ['active', 'deactivated'].forEach((status) => {
        statusSelect.append('<option value="' + status + '">' + escapeHtml(statusObj[status].title) + '</option>');
      });
    }
  });
});
