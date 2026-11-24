/**
 * The driver's trips table (FRD V01.14 §1.14 "بيان (جدول) بالرحلات مرتبة زمنيا من الأحدث للأقدم", part 7.6) —
 * /api/v1/trips, which returns only the trips of this driver (Trip::scopeVisibleTo) with the fields he may see
 * (no costs, no PO number). Newest first; search by trip number, project, vehicle or service; "add trip".
 * Mobile first: the responsive table folds the secondary columns into a row's detail.
 */
'use strict';

$(function () {
  const t = window.driverTripListTranslations || {};
  const dt = $('.datatables-driver-trips');
  if (!dt.length) return;

  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();
  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };

  const filters = { status: '' };
  const listParams = () => ({ status: filters.status || undefined });

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/trips', listParams, t.no_permission),
    columns: [
      { data: 'id' }, { data: 'code' }, { data: 'trip_at' }, { data: 'project' },
      { data: 'vehicle' }, { data: 'sub_service' }, { data: 'status' }, { data: 'id' }
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
      { targets: 3, orderable: false, render: (data, type, full) => escapeHtml(full.project && full.project.name) },
      { targets: 4, orderable: false, render: (data, type, full) => '<span dir="ltr">' + escapeHtml(full.vehicle && full.vehicle.plate) + '</span>' },
      { targets: 5, orderable: false, render: (data, type, full) => escapeHtml(full.sub_service ? full.sub_service.name : '—') },
      {
        targets: 6,
        responsivePriority: 3,
        render: (data, type, full) =>
          type === 'display'
            ? '<span class="badge ' + (statusClass[full.status] || 'bg-label-secondary') + '">' + escapeHtml(full.status_label) + '</span>'
            : full.status
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
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + escapeHtml(t.add_trip) + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => {
          window.location.href = t.add_url;
        }
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
      const select = $('<select class="form-select"><option value="">' + escapeHtml(t.all_statuses) + '</option></select>')
        .appendTo('.dt_status')
        .on('change', function () {
          filters.status = $(this).val();
          api.draw();
        });
      Object.entries(t.statuses || {}).forEach(([value, label]) => select.append($('<option>').val(value).text(label)));
    }
  });
});
