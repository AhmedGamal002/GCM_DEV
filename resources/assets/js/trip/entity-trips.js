/**
 * The "Trips" table of a vehicle's and a driver's details page (FRD V01.14 §1.5 / §1.6: "جدول يشمل الرحلات …
 * قابل للـ (البحث / التصفية / التصدير)") — the trips of ONE vehicle or ONE driver, from /api/v1/trips with
 * `vehicle_id` / `driver_id` fixed, so the rows, the scope (Trip::scopeVisibleTo: a contractor user only sees his
 * own) and the fields (TripFieldVisibility) are exactly the trips list's. Filters: status, time range and — for the
 * roles that may read the lists behind them — driver (vehicle page) / service (driver page), client and project.
 * The export is the same list: gcmExport with the same params.
 *
 * `window.entityTripsConfig` (set by tenant/trips/_entity-trips.blade.php): {kind: 'vehicle'|'driver', entityId,
 * viewUrlBase, refFilters, t}.
 */
'use strict';

export function initEntityTrips() {
  const cfg = window.entityTripsConfig;
  const table = $('#et-table');
  if (!cfg || !table.length) return;

  const t = cfg.t;
  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();
  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };
  const byVehicle = cfg.kind === 'vehicle';

  const filters = { status: '', company_id: '', project_id: '', driver_id: '', main_service_id: '' };
  const dateFrom = document.getElementById('et-date-from');
  const dateTo = document.getElementById('et-date-to');

  // One list of params feeds the table and the export (CLAUDE.md: the export must match the screen).
  const listParams = () => {
    const params = { [byVehicle ? 'vehicle_id' : 'driver_id']: cfg.entityId, date_from: (dateFrom && dateFrom.value) || undefined, date_to: (dateTo && dateTo.value) || undefined };
    Object.keys(filters).forEach((key) => {
      // on the driver page the driver is fixed; the picker below only exists on the vehicle page
      if (key === 'driver_id' && !byVehicle) return;
      params[key] = filters[key] || undefined;
    });

    return params;
  };

  const link = (full) => cfg.viewUrlBase + '/' + full.id;
  const other = byVehicle
    ? { key: 'driver', title: t.driver, render: (full) => escapeHtml(full.driver && full.driver.name) }
    : { key: 'vehicle', title: t.vehicle, render: (full) => '<span dir="ltr">' + escapeHtml(full.vehicle && full.vehicle.plate) + '</span>' };

  table.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/trips', listParams, t.no_permission),
    columns: [{ data: 'code' }, { data: 'trip_at' }, { data: other.key }, { data: 'company' }, { data: 'project' }, { data: 'status' }, { data: 'id' }],
    columnDefs: [
      { targets: 0, responsivePriority: 1, render: (data, type, full) => '<a href="' + link(full) + '" class="fw-medium" dir="ltr">' + escapeHtml(full.code) + '</a>' },
      {
        targets: 1,
        render: (data, type, full) => (type === 'display' ? '<span dir="ltr">' + escapeHtml(full.trip_at ? moment(full.trip_at).format('YYYY-MM-DD HH:mm') : '') + '</span>' : full.trip_at)
      },
      { targets: 2, orderable: false, render: (data, type, full) => other.render(full) },
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.company && full.company.name) },
      { targets: 4, orderable: false, render: (data, type, full) => escapeHtml(full.project && full.project.name) },
      {
        targets: 5,
        render: (data, type, full) =>
          type === 'display' ? '<span class="badge ' + (statusClass[full.status] || 'bg-label-secondary') + '">' + escapeHtml(full.status_label) + '</span>' : full.status
      },
      {
        targets: -1,
        searchable: false,
        orderable: false,
        render: (data, type, full) =>
          '<a href="' + link(full) + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' + escapeHtml(t.view) + '"><i class="ti ti-eye ti-md"></i></a>'
      }
    ],
    order: [[1, 'desc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search,
      emptyTable: t.no_trips,
      info: t.info,
      infoEmpty: t.info_empty,
      paginate: { next: '<i class="ti ti-chevron-right ti-sm"></i>', previous: '<i class="ti ti-chevron-left ti-sm"></i>' }
    },
    dom:
      '<"row mx-2 my-3"<"col-md-2"<"ms-n2"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"fB>>>t' +
      '<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
    buttons: [
      {
        extend: 'collection',
        className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
        text: '<i class="ti ti-upload me-2 ti-xs"></i>' + t.export,
        buttons: [
          { text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel', className: 'dropdown-item', action: (e, api) => window.gcmExport('/api/v1/trips/export', 'xlsx', api, listParams) },
          { text: '<i class="ti ti-file-code-2 me-2"></i>Pdf', className: 'dropdown-item', action: (e, api) => window.gcmExport('/api/v1/trips/export', 'pdf', api, listParams) }
        ]
      }
    ],
    initComplete: function () {
      const api = this.api();

      const status = $('#et-status').on('change', function () {
        filters.status = $(this).val();
        api.draw();
      });
      Object.entries(t.statuses || {}).forEach(([value, label]) => status.append($('<option>').val(value).text(label)));
      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));

      /** A live-search list over an ordinary list endpoint (`{data, meta}`), cleared with the x. */
      const live = (selector, placeholder, key, url, label, extra, onChange) => {
        const $el = $(selector);
        if (!$el.length) return null;
        $el.wrap('<div class="position-relative"></div>').select2({
          dropdownParent: $el.parent(),
          width: '100%',
          allowClear: true,
          placeholder,
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

      // Only the roles that may read those lists get the pickers (a contractor user can't read clients, projects or
      // services). Set up after the page loader is gone, like the trips list does (CLAUDE.md).
      const setUp = () => {
        if (!cfg.refFilters) return;
        const project = live('#et-project', t.all_projects, 'project_id', '/api/v1/projects', (p) => p.name, () => ({ company_id: filters.company_id || undefined }));
        live('#et-company', t.all_clients, 'company_id', '/api/v1/companies', (c) => c.name, null, () => {
          filters.project_id = '';
          if (project) project.val(null).trigger('change');
        });
        live('#et-driver', t.all_drivers, 'driver_id', '/api/v1/drivers', (d) => d.name + (d.code ? ' (' + d.code + ')' : ''));
        live('#et-service', t.all_services, 'main_service_id', '/api/v1/main-services', (s) => s.name);
      };

      if (window.gcmPageLoader && window.gcmPageLoader.whenReady) window.gcmPageLoader.whenReady(setUp);
      else setUp();
    }
  });
}
