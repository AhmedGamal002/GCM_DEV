/**
 * GCM's trips list (FRD V01.14 §1.14 "إدارة الرحلات", part 7.4) — /api/v1/trips with the five counters from
 * /stats, the twelve columns of the FRD, the filters (time range, driver, vehicle, both affiliations, client,
 * project, service, status), the search box, "add trip" (System Admin / Data Entry) and the export. Newest first.
 *
 * The filter lists come from the ordinary list endpoints (the auditor sees them too, unlike /trips/options/*):
 * the long ones (clients, projects, drivers, vehicles, services) are live-search Select2s that page through the
 * endpoint instead of loading every row.
 */
'use strict';

$(function () {
  const t = window.tripListTranslations || {};
  const dt = $('.datatables-trips');

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

  const filters = {
    status: '',
    company_id: '',
    project_id: '',
    driver_id: '',
    vehicle_id: '',
    driver_affiliation: '',
    vehicle_affiliation: '',
    main_service_id: '',
    sub_service_id: ''
  };
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
      { data: 'id' },
      { data: 'code' },
      { data: 'trip_at' },
      { data: 'driver' },
      { data: 'driver' },
      { data: 'vehicle' },
      { data: 'vehicle' },
      { data: 'company' },
      { data: 'project' },
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
        // FRD: "linked to PO No. …" / "not linked". Staff see the number (ج18).
        targets: 11,
        orderable: false,
        render: function (data, type, full) {
          if (!full.linked_to_purchase_order) return '<span class="text-muted">' + escapeHtml(t.not_linked) + '</span>';
          return full.purchase_order ? escapeHtml(t.linked_to) + ' <span dir="ltr">' + escapeHtml(full.purchase_order.number) + '</span>' : escapeHtml(t.linked);
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const edit =
            t.can_manage && full.actions && full.actions.update
              ? '<a href="' + t.edit_url_base + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
                escapeHtml(t.edit) + '"><i class="ti ti-edit ti-md"></i></a>'
              : '';

          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + t.view_url_base + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            escapeHtml(t.view) + '"><i class="ti ti-eye ti-md"></i></a>' +
            edit +
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
    ].concat(
      t.can_manage
        ? [
            {
              text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + escapeHtml(t.add_trip) + '</span>',
              className: 'add-new btn btn-primary waves-effect waves-light',
              action: () => {
                window.location.href = t.add_url;
              }
            }
          ]
        : []
    ),
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

      const holder = (cls) => $('.' + cls);
      const plain = (cls, allLabel, key, options) => {
        const select = $('<select class="form-select"><option value="">' + escapeHtml(allLabel) + '</option></select>')
          .appendTo(holder(cls))
          .on('change', function () {
            filters[key] = $(this).val();
            api.draw();
          });
        Object.entries(options || {}).forEach(([value, label]) => select.append($('<option>').val(value).text(label)));

        return select;
      };

      plain('trip_status', t.all_statuses, 'status', t.statuses);
      plain('trip_driver_aff', t.driver_affiliation, 'driver_affiliation', { gcm: t.gcm, contractor: t.contractor });
      plain('trip_vehicle_aff', t.vehicle_affiliation, 'vehicle_affiliation', { gcm: t.gcm, contractor: t.contractor });

      /** A live-search list over an ordinary list endpoint (`{data, meta}`), cleared with the x. */
      const live = (cls, allLabel, key, url, label, extra, onChange) => {
        const $el = $('<select class="form-select"><option value=""></option></select>').appendTo(holder(cls));
        $el.wrap('<div class="position-relative"></div>').select2({
          dropdownParent: $el.parent(),
          width: '100%',
          allowClear: true,
          placeholder: allLabel,
          ajax: {
            delay: 250,
            cache: false,
            transport: (params, success, failure) =>
              window.axios.get(url, { params: Object.assign({ search: params.data.search, page: params.data.page, per_page: 20 }, extra ? extra() : {}) }).then(success).catch(failure),
            data: (params) => ({ search: params.term || '', page: params.page || 1 }),
            processResults: (response) => ({
              results: response.data.data.map((row) => ({ id: row.id, text: label(row) })),
              pagination: { more: response.data.meta.current_page < response.data.meta.last_page }
            })
          }
        });
        $el.on('select2:select select2:clear', function () {
          filters[key] = $el.val() || '';
          if (onChange) onChange();
          api.draw();
        });

        return $el;
      };

      // Lookups that aren't needed to draw the page are set up after the page loader is gone (CLAUDE.md).
      const setUp = () => {
        const project = live('trip_project', t.all_projects, 'project_id', '/api/v1/projects', (p) => p.name, () => ({ company_id: filters.company_id || undefined }));
        live('trip_company', t.all_clients, 'company_id', '/api/v1/companies', (c) => c.name, null, () => {
          // the project list follows the client: a project of another client no longer applies
          filters.project_id = '';
          project.val(null).trigger('change');
        });
        live('trip_driver', t.all_drivers, 'driver_id', '/api/v1/drivers', (d) => d.name + (d.code ? ' (' + d.code + ')' : ''));
        live('trip_vehicle', t.all_vehicles, 'vehicle_id', '/api/v1/vehicles', (v) => v.plate);
        const sub = live('trip_sub_service', t.all_sub_services, 'sub_service_id', '/api/v1/sub-services', (s) => s.name, () => ({ main_service_id: filters.main_service_id || undefined }));
        live('trip_main_service', t.all_main_services, 'main_service_id', '/api/v1/main-services', (s) => s.name, null, () => {
          filters.sub_service_id = '';
          sub.val(null).trigger('change');
        });
      };

      if (window.gcmPageLoader && window.gcmPageLoader.whenReady) window.gcmPageLoader.whenReady(setUp);
      else setUp();

      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));
    }
  });
});
