/**
 * Vehicles DataTable — same DataTables layout as app-user-list.js
 * (search + export in one row, filters built from the loaded data,
 * "Processing" indicator), wired to /api/v1/vehicles. Columns match the
 * FRD §1.5.3 list-table spec: Plate / Category / Trips / Affiliation /
 * Availability / Actions. The "Actions" column links to the dedicated
 * details page — editing and status changes happen there.
 */
'use strict';

$(function () {
  const t = window.vehicleListTranslations || {};
  const dt = $('.datatables-vehicles');

  // plate is user-entered and this render path builds raw HTML strings,
  // so escape it explicitly — DataTables inserts via .html(), not text.
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    on_maintenance: { title: t.on_maintenance || 'On Maintenance', class: 'bg-label-warning' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('vehicle-list-status');
    box.textContent = t.created || 'Vehicle created successfully.';
    box.classList.remove('d-none');
  }

  // Stat cards
  window.axios
    .get('/api/v1/vehicles/stats')
    .then(function (response) {
      const s = response.data.data;
      Object.entries(s.availability).forEach(([key, val]) => {
        const el = document.querySelector(`#vehicle-stats [data-stat="${key}"]`);
        if (el) el.textContent = val;
      });
      Object.entries(s.by_category).forEach(([slug, val]) => {
        const el = document.querySelector(`#vehicle-category-stats [data-cat="${slug}"]`);
        if (el) el.textContent = val;
      });
    })
    .catch(() => {});

  if (!dt.length) {
    return;
  }

  dt.DataTable({
    processing: true,
    ajax: {
      url: '/api/v1/vehicles?per_page=1000',
      dataSrc: 'data'
    },
    columns: [
      { data: 'id' },
      { data: 'id' },
      { data: 'plate' },
      { data: 'category' },
      { data: 'trips_count' },
      { data: 'affiliation' },
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
        responsivePriority: 4,
        render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.plate) + '</span>'
      },
      {
        targets: 3,
        render: function (data, type, full) {
          const slug = full.category && full.category.slug;
          const label = (t.categories && t.categories[slug]) || (full.category && full.category.name) || slug || '';
          return escapeHtml(label);
        }
      },
      {
        targets: 4,
        render: (data, type, full) => full.trips_count || 0
      },
      {
        targets: 5,
        render: (data, type, full) => (full.affiliation === 'gcm' ? t.gcm || 'GCM' : t.contractor || full.affiliation)
      },
      {
        targets: 6,
        render: function (data, type, full) {
          const s = statusObj[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + s.title + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          const viewUrl = (t.view_url_base || '/app/vehicle/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/vehicle/edit') + '/' + full.id;
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
    order: [[2, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_vehicle || 'Search Vehicle',
      emptyTable: t.no_vehicles_found || 'No vehicles found.',
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
            action: () => window.location.assign('/api/v1/vehicles/export?format=xlsx')
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: () => window.location.assign('/api/v1/vehicles/export?format=pdf')
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_vehicle || 'Add Vehicle') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_vehicle_url || '/app/vehicle/add')
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => (t.view || 'Details') + ' — ' + row.data().plate
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

      // Category filter — matches on the rendered category label, same
      // approach as the users table's role filter.
      api.columns(3).every(function () {
        const column = this;
        const select = $(
          '<select class="form-select"><option value="">' + (t.all_categories || 'All categories') + '</option></select>'
        )
          .appendTo('.vehicle_category')
          .on('change', function () {
            const val = $.fn.dataTable.util.escapeRegex($(this).val());
            column.search(val ? '^' + val + '$' : '', true, false).draw();
          });

        const seen = new Set();
        column.data().each((cat) => {
          if (cat && cat.slug) seen.add(cat.slug);
        });
        Array.from(seen)
          .sort()
          .forEach((slug) => {
            const label = (t.categories && t.categories[slug]) || slug;
            select.append('<option value="' + label + '">' + label + '</option>');
          });
      });

      // Status filter
      api.columns(6).every(function () {
        const column = this;
        const select = $(
          '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
        )
          .appendTo('.vehicle_status')
          .on('change', function () {
            const val = $.fn.dataTable.util.escapeRegex($(this).val());
            column.search(val ? '^' + val + '$' : '', true, false).draw();
          });

        ['active', 'on_maintenance', 'deactivated'].forEach((status) => {
          select.append('<option value="' + status + '">' + statusObj[status].title + '</option>');
        });
      });

      // Affiliation filter
      api.columns(5).every(function () {
        const column = this;
        const select = $(
          '<select class="form-select"><option value="">' + (t.all_affiliations || 'All affiliations') + '</option></select>'
        )
          .appendTo('.vehicle_affiliation')
          .on('change', function () {
            const val = $.fn.dataTable.util.escapeRegex($(this).val());
            column.search(val ? '^' + val + '$' : '', true, false).draw();
          });

        select.append('<option value="gcm">' + (t.gcm || 'GCM') + '</option>');
        select.append('<option value="contractor">' + (t.contractor || 'Contractor') + '</option>');
      });
    }
  });
});
