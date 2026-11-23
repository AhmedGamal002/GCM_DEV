/**
 * The client's trips list (FRD V01.14 §1.14 "مراجعة العميل للرحلات", part 7.5) — /api/v1/trips, which already
 * returns only the trips of the projects this account sees (Trip::scopeVisibleTo) with the fields it may see
 * (ج18: the PO number for the project manager only — the auditor gets "linked" / "not linked").
 *
 * Columns follow the FRD's client list: trip No. / date / driver / vehicle / project / default client approver /
 * sub-service / status / PO / details. Filters: time range, project, service, status (the lists come from
 * /api/v1/trips/client-filters — the client has no access to the staff's project / service lists). Four
 * counters from /api/v1/trips/stats. Newest first (FRD). Export (Excel / PDF) with the same filters.
 */
'use strict';

$(function () {
  const t = window.clientTripListTranslations || {};
  const dt = $('.datatables-client-trips');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };

  window.axios
    .get('/api/v1/trips/stats')
    .then(function (response) {
      (response.data.data.by_status || []).forEach(function (s) {
        const el = document.querySelector('[data-stat="' + s.status + '"]');
        if (el) el.textContent = s.count;
      });
    })
    .catch(() => {});

  if (!dt.length) return;

  const filters = { project_id: '', main_service_id: '', sub_service_id: '', status: '' };
  const dateFrom = document.getElementById('ct-date-from');
  const dateTo = document.getElementById('ct-date-to');

  const listParams = () => ({
    project_id: filters.project_id || undefined,
    main_service_id: filters.main_service_id || undefined,
    sub_service_id: filters.sub_service_id || undefined,
    status: filters.status || undefined,
    date_from: (dateFrom && dateFrom.value) || undefined,
    date_to: (dateTo && dateTo.value) || undefined
  });

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/trips', listParams, t.no_permission),
    columns: [
      { data: 'id' },
      { data: 'code' },
      { data: 'trip_at' },
      { data: 'driver' },
      { data: 'vehicle' },
      { data: 'project' },
      { data: 'client_approver' },
      { data: 'sub_service' },
      { data: 'status' },
      { data: 'linked_to_purchase_order' },
      { data: 'id' }
    ],
    columnDefs: [
      { className: 'control', searchable: false, orderable: false, responsivePriority: 2, targets: 0, render: () => '' },
      {
        targets: 1,
        responsivePriority: 1,
        render: (data, type, full) =>
          '<a href="' + t.view_url_base + '/' + full.id + '" class="fw-medium" dir="ltr">' + escapeHtml(full.code) + '</a>'
      },
      {
        targets: 2,
        render: (data, type, full) =>
          type === 'display' ? '<span dir="ltr">' + escapeHtml(full.trip_at ? moment(full.trip_at).format('YYYY-MM-DD HH:mm') : '') + '</span>' : full.trip_at
      },
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.driver && full.driver.name) },
      { targets: 4, orderable: false, render: (data, type, full) => '<span dir="ltr">' + escapeHtml(full.vehicle && full.vehicle.plate) + '</span>' },
      { targets: 5, orderable: false, render: (data, type, full) => escapeHtml(full.project && full.project.name) },
      { targets: 6, orderable: false, render: (data, type, full) => escapeHtml(full.client_approver ? full.client_approver.name : '—') },
      { targets: 7, orderable: false, render: (data, type, full) => escapeHtml(full.sub_service ? full.sub_service.name : '—') },
      {
        targets: 8,
        render: (data, type, full) =>
          type === 'display'
            ? '<span class="badge ' + (statusClass[full.status] || 'bg-label-secondary') + '">' + escapeHtml(full.status_label) + '</span>'
            : full.status
      },
      {
        // ج18: the manager sees which PO; the auditor only whether there is one.
        targets: 9,
        orderable: false,
        render: function (data, type, full) {
          if (!full.linked_to_purchase_order) return '<span class="text-muted">' + escapeHtml(t.not_linked) + '</span>';
          return full.purchase_order ? '<span dir="ltr">' + escapeHtml(full.purchase_order.number) + '</span>' : escapeHtml(t.linked);
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const review =
            full.client_actions && full.client_actions.approve
              ? '<a href="' + t.review_url_base + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
                escapeHtml(t.review) + '"><i class="ti ti-signature ti-md"></i></a>'
              : '';
          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + t.view_url_base + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            escapeHtml(t.view) + '"><i class="ti ti-eye ti-md"></i></a>' +
            review +
            '</div>'
          );
        }
      }
    ],
    order: [[2, 'desc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search || 'Search Trip',
      emptyTable: t.no_trips_found || 'No trips found.',
      info: t.info,
      infoEmpty: t.info_empty,
      paginate: { next: '<i class="ti ti-chevron-right ti-sm"></i>', previous: '<i class="ti ti-chevron-left ti-sm"></i>' }
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
          { text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel', className: 'dropdown-item', action: (e, api) => window.gcmExport('/api/v1/trips/export', 'xlsx', api, listParams) },
          { text: '<i class="ti ti-file-code-2 me-2"></i>Pdf', className: 'dropdown-item', action: (e, api) => window.gcmExport('/api/v1/trips/export', 'pdf', api, listParams) }
        ]
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({ header: (row) => (t.view || 'Details') + ' — ' + row.data().code }),
        type: 'column',
        renderer: function (api, rowIdx, columns) {
          const data = $.map(columns, (col) =>
            col.title !== ''
              ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '"><td>' + col.title + ':</td><td>' + col.data + '</td></tr>'
              : ''
          ).join('');
          return data ? $('<table class="table"/><tbody />').append(data) : false;
        }
      }
    },
    initComplete: function () {
      const api = this.api();

      const select = (holder, allLabel, key) =>
        $('<select class="form-select"><option value="">' + escapeHtml(allLabel) + '</option></select>')
          .appendTo(holder)
          .on('change', function () {
            filters[key] = $(this).val();
            api.draw();
          });

      const status = select('.ct_status', t.all_statuses, 'status');
      Object.entries(t.statuses || {}).forEach(([value, label]) => status.append($('<option>').val(value).text(label)));

      const project = select('.ct_project', t.all_projects, 'project_id');
      const main = select('.ct_main_service', t.all_main_services, 'main_service_id');
      const sub = select('.ct_sub_service', t.all_sub_services, 'sub_service_id');

      // Lookups that aren't needed to draw the page load after the page loader is gone (CLAUDE.md).
      const loadLookups = () =>
        window.axios
          .get('/api/v1/trips/client-filters')
          .then((response) => {
            const data = response.data.data || {};
            (data.projects || []).forEach((p) => project.append($('<option>').val(p.id).text(p.name)));
            (data.main_services || []).forEach((s) => main.append($('<option>').val(s.id).text(s.name)));
            (data.sub_services || []).forEach((s) => sub.append($('<option>').val(s.id).text(s.name)));
          })
          .catch(() => {});

      if (window.gcmPageLoader && window.gcmPageLoader.whenReady) window.gcmPageLoader.whenReady(loadLookups);
      else loadLookups();

      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));
    }
  });
});
