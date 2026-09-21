/**
 * Drivers DataTable — matches the FRD's dedicated "إدارة السائقين" page
 * spec exactly: 4 stat cards (available / on trips / on vacation /
 * deactivated), a 5-column table (ID / Driver Name / Affiliation /
 * Driver Availability / Details), filters (Affiliation + Availability),
 * and an Export button — a DIFFERENT, smaller column set than the
 * general Users table (no Entity/Role columns — this page is
 * driver-only, so "Role" is always "driver" and redundant here).
 */
'use strict';

$(function () {
  const t = window.driverListTranslations || {};
  const dtDriverTable = $('.datatables-drivers');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    on_vacation: { title: t.on_vacation || 'On Vacation', class: 'bg-label-warning' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('driver-list-status');
    box.textContent = t.created || 'Driver created successfully.';
    box.classList.remove('d-none');
  }

  if (!dtDriverTable.length) {
    return;
  }

  // Stat cards — a real endpoint now (GET /api/v1/drivers/stats), not
  // counted from whatever page of the table happened to be loaded. That
  // used to silently under-count past the first ~1000 drivers — see
  // datatables-server-side.js's docblock for the full story.
  window.axios
    .get('/api/v1/drivers/stats')
    .then(function (response) {
      const s = response.data.data;
      document.getElementById('dl-stat-available').textContent = s.available;
      document.getElementById('dl-stat-vacation').textContent = s.on_vacation;
      document.getElementById('dl-stat-deactivated').textContent = s.deactivated;
      // "On Trips" stays 0 — no Trips module yet (Week 7).
    })
    .catch(() => {});

  let currentAffiliation = '';
  let currentStatus = '';

  // Filters currently applied to the list — the table's own requests AND the
  // Excel/PDF export read this, so an export always matches what is on screen.
  const listParams = () => ({
    affiliation: currentAffiliation || undefined,
    status: currentStatus || undefined
  });

  dtDriverTable.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/drivers', listParams, t.no_permission),
    columns: [
      { data: 'id' },
      { data: 'id' },
      { data: 'code' },
      { data: 'name' },
      { data: 'affiliation' },
      { data: 'status' },
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
        targets: 3,
        responsivePriority: 4,
        render: function (data, type, full) {
          const initials = (full.name.match(/\b\w/g) || []).slice(0, 2).join('').toUpperCase();
          return (
            '<div class="d-flex justify-content-start align-items-center user-name">' +
            '<div class="avatar-wrapper"><div class="avatar avatar-sm me-4">' +
            (full.photo_url
              ? '<img src="' + full.photo_url + '" alt="Avatar" class="rounded-circle">'
              : '<span class="avatar-initial rounded-circle bg-label-primary">' + initials + '</span>') +
            '</div></div>' +
            '<div class="d-flex flex-column">' +
            '<span class="text-heading fw-medium">' + escapeHtml(full.name) + '</span>' +
            '<small>' + escapeHtml(full.email) + '</small>' +
            '</div></div>'
          );
        }
      },
      {
        targets: 4,
        orderable: false,
        render: (data, type, full) => (full.affiliation === 'gcm' ? 'GCM' : full.affiliation)
      },
      {
        targets: 5,
        render: function (data, type, full) {
          const status = statusObj[full.status] || { title: full.status, class: 'bg-label-secondary' };
          return '<span class="badge ' + status.class + '">' + status.title + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const viewUrl = (t.view_url_base || '/app/driver/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/driver/edit') + '/' + full.id;
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
      searchPlaceholder: t.search_driver || 'Search Driver',
      emptyTable: t.no_drivers_found || 'No drivers found.',
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
            action: (e, dt) => window.gcmExport('/api/v1/drivers/export', 'xlsx', dt, listParams)
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/drivers/export', 'pdf', dt, listParams)
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_driver || 'Add Driver') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_driver_url || '/app/driver/add')
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => 'Details of ' + row.data().name
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

      // Affiliation filter — FRD: "التبعية (للشركة / لمتعهد)". Fixed
      // list now (server-side paging means the loaded page never has
      // every affiliation value in it to build this from anymore).
      const affiliationSelect = $(
        '<select class="form-select"><option value="">' + (t.all_affiliations || 'All affiliations') + '</option></select>'
      )
        .appendTo('.driver_affiliation')
        .on('change', function () {
          currentAffiliation = $(this).val();
          api.draw();
        });
      ['gcm', 'contractor'].forEach((affiliation) => {
        const label = affiliation === 'gcm' ? 'GCM' : affiliation;
        affiliationSelect.append('<option value="' + affiliation + '">' + label + '</option>');
      });

      // Availability filter — FRD: "توفر السائق"
      const statusSelect = $(
        '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
      )
        .appendTo('.driver_status')
        .on('change', function () {
          currentStatus = $(this).val();
          api.draw();
        });
      ['active', 'on_vacation', 'deactivated'].forEach((status) => {
        statusSelect.append('<option value="' + status + '">' + statusObj[status].title + '</option>');
      });
    }
  });
});
