/**
 * Assets DataTable — same server-side setup as app-vehicle-list.js, wired
 * to /api/v1/assets. Columns match the FRD §1.7.3 list spec: Name /
 * Capacity / Type / Affiliation / Availability / Actions. The "Actions"
 * column links to the dedicated details page — editing and status
 * changes happen there.
 */
'use strict';

$(function () {
  const t = window.assetListTranslations || {};
  const dt = $('.datatables-assets');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    on_maintenance: { title: t.on_maintenance || 'On Maintenance', class: 'bg-label-warning' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  const typeLabels = { container: t.container || 'Container', tank: t.tank || 'Tank' };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('asset-list-status');
    box.textContent = t.created || 'Asset created successfully.';
    box.classList.remove('d-none');
  }

  // Stat cards — container + tank groups, 4 counts each.
  window.axios
    .get('/api/v1/assets/stats')
    .then(function (response) {
      const s = response.data.data;
      ['container', 'tank'].forEach((type) => {
        Object.entries(s[type] || {}).forEach(([key, val]) => {
          const el = document.querySelector(`[data-asset-stats-group="${type}"] [data-stat="${type}.${key}"]`);
          if (el) el.textContent = val;
        });
      });
    })
    .catch(() => {});

  if (!dt.length) {
    return;
  }

  let currentType = '';
  let currentStatus = '';
  let currentAffiliation = '';

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/assets', () => ({
      asset_type: currentType || undefined,
      operational_status: currentStatus || undefined,
      affiliation: currentAffiliation || undefined
    })),
    columns: [
      { data: 'id' },
      { data: 'id' },
      { data: 'name' },
      { data: 'capacity_category' },
      { data: 'asset_type' },
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
        render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>'
      },
      {
        targets: 3,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.capacity_category && full.capacity_category.name)
      },
      {
        targets: 4,
        render: (data, type, full) => (type === 'display' ? escapeHtml(typeLabels[full.asset_type] || full.asset_type) : full.asset_type)
      },
      {
        targets: 5,
        orderable: false,
        render: (data, type, full) => (full.affiliation === 'gcm' ? t.gcm || 'GCM' : t.contractor || full.affiliation)
      },
      {
        targets: 6,
        render: function (data, type, full) {
          if (type !== 'display') return full.operational_status;
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
          const viewUrl = (t.view_url_base || '/app/asset/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/asset/edit') + '/' + full.id;
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
      searchPlaceholder: t.search_asset || 'Search Asset',
      emptyTable: t.no_assets_found || 'No assets found.',
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
            action: () => window.location.assign('/api/v1/assets/export?format=xlsx')
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: () => window.location.assign('/api/v1/assets/export?format=pdf')
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_asset || 'Add Asset') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_asset_url || '/app/asset/add')
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

      const typeSelect = $(
        '<select class="form-select"><option value="">' + (t.all_types || 'All types') + '</option></select>'
      )
        .appendTo('.asset_type')
        .on('change', function () {
          currentType = $(this).val();
          api.draw();
        });
      ['container', 'tank'].forEach((type) => {
        typeSelect.append('<option value="' + type + '">' + (typeLabels[type] || type) + '</option>');
      });

      const statusSelect = $(
        '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
      )
        .appendTo('.asset_status')
        .on('change', function () {
          currentStatus = $(this).val();
          api.draw();
        });
      ['active', 'on_maintenance', 'deactivated'].forEach((status) => {
        statusSelect.append('<option value="' + status + '">' + statusObj[status].title + '</option>');
      });

      const affiliationSelect = $(
        '<select class="form-select"><option value="">' + (t.all_affiliations || 'All affiliations') + '</option></select>'
      )
        .appendTo('.asset_affiliation')
        .on('change', function () {
          currentAffiliation = $(this).val();
          api.draw();
        });
      affiliationSelect.append('<option value="gcm">' + (t.gcm || 'GCM') + '</option>');
      affiliationSelect.append('<option value="contractor">' + (t.contractor || 'Contractor') + '</option>');
    }
  });
});
