/**
 * The contractor user's trips list (FRD V01.14 §1.14 "من منظور دور مستخدم المتعهد", part 7.7) — view and export only:
 * /api/v1/trips, which sends only the trips whose driver or vehicle belongs to his contractor (Trip::scopeVisibleTo)
 * with what a field-side user may see (no costs, no PO number — "linked / not linked" only). The five counters from
 * /stats, filters (time range, status, both affiliations), the search box and the export of the same list.
 */
'use strict';

$(function () {
  const t = window.contractorTripListTranslations || {};
  const dt = $('.datatables-trips');

  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();
  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };

  window.axios
    .get('/api/v1/trips/stats')
    .then((response) => {
      (response.data.data.by_status || []).forEach((s) => {
        const el = document.querySelector('[data-stat="' + s.status + '"]');
        if (el) el.textContent = s.count;
      });
    })
    .catch(() => {});

  if (!dt.length) return;

  const filters = { status: '', driver_affiliation: '', vehicle_affiliation: '' };
  const dateFrom = document.getElementById('trip-date-from');
  const dateTo = document.getElementById('trip-date-to');

  // One list of params feeds the table and the export (CLAUDE.md: the export must match the screen).
  const listParams = () => {
    const params = { date_from: (dateFrom && dateFrom.value) || undefined, date_to: (dateTo && dateTo.value) || undefined };
    Object.keys(filters).forEach((key) => {
      params[key] = filters[key] || undefined;
    });

    return params;
  };

  /** Affiliation cell: "GCM" or the contractor's name. */
  const affiliation = (entity) => {
    if (!entity) return '—';
    const label = entity.affiliation === 'gcm' ? t.gcm : entity.entity_name || t.contractor;
    const badge = entity.affiliation === 'gcm' ? 'bg-label-primary' : 'bg-label-warning';

    return '<span class="badge ' + badge + '">' + escapeHtml(label) + '</span>';
  };

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/trips', listParams, t.no_permission),
    columns: [
      { data: 'id' }, { data: 'code' }, { data: 'trip_at' }, { data: 'driver' }, { data: 'driver' }, { data: 'vehicle' },
      { data: 'vehicle' }, { data: 'company' }, { data: 'project' }, { data: 'sub_service' }, { data: 'status' },
      { data: 'linked_to_purchase_order' }, { data: 'id' }
    ],
    columnDefs: [
      { className: 'control', searchable: false, orderable: false, responsivePriority: 2, targets: 0, render: () => '' },
      {
        targets: 1,
        responsivePriority: 1,
        render: (data, type, full) => '<a href="' + t.view_url_base + '/' + full.id + '" class="fw-medium" dir="ltr">' + escapeHtml(full.code) + '</a>'
      },
      {
        targets: 2,
        render: (data, type, full) =>
          type === 'display' ? '<span dir="ltr">' + escapeHtml(full.trip_at ? moment(full.trip_at).format('YYYY-MM-DD HH:mm') : '') + '</span>' : full.trip_at
      },
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.driver && full.driver.name) },
      { targets: 4, orderable: false, render: (data, type, full) => affiliation(full.driver) },
      { targets: 5, orderable: false, render: (data, type, full) => '<span dir="ltr">' + escapeHtml(full.vehicle && full.vehicle.plate) + '</span>' },
      { targets: 6, orderable: false, render: (data, type, full) => affiliation(full.vehicle) },
      { targets: 7, orderable: false, render: (data, type, full) => escapeHtml(full.company && full.company.name) },
      { targets: 8, orderable: false, render: (data, type, full) => escapeHtml(full.project && full.project.name) },
      { targets: 9, orderable: false, render: (data, type, full) => escapeHtml(full.sub_service ? full.sub_service.name : '—') },
      {
        targets: 10,
        render: (data, type, full) =>
          type === 'display'
            ? '<span class="badge ' + (statusClass[full.status] || 'bg-label-secondary') + '">' + escapeHtml(full.status_label) + '</span>'
            : full.status
      },
      {
        // ج18: the PO number is for GCM and the client's project manager — he only sees whether the trip is linked.
        targets: 11,
        orderable: false,
        render: (data, type, full) => (full.linked_to_purchase_order ? escapeHtml(t.linked) : '<span class="text-muted">' + escapeHtml(t.not_linked) + '</span>')
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: (data, type, full) =>
          '<a href="' + t.view_url_base + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
          escapeHtml(t.view) + '"><i class="ti ti-eye ti-md"></i></a>'
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
      '<"row"<"col-md-2"<"ms-n2"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"fB>>>t' +
      '<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
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
      const plain = (cls, allLabel, key, options) => {
        const select = $('<select class="form-select"><option value="">' + escapeHtml(allLabel) + '</option></select>')
          .appendTo('.' + cls)
          .on('change', function () {
            filters[key] = $(this).val();
            api.draw();
          });
        Object.entries(options || {}).forEach(([value, label]) => select.append($('<option>').val(value).text(label)));
      };

      plain('trip_status', t.all_statuses, 'status', t.statuses);
      plain('trip_driver_aff', t.driver_affiliation, 'driver_affiliation', { gcm: t.gcm, contractor: t.contractor });
      plain('trip_vehicle_aff', t.vehicle_affiliation, 'vehicle_affiliation', { gcm: t.gcm, contractor: t.contractor });

      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));
    }
  });
});
