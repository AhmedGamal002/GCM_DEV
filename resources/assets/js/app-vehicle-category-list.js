/**
 * Vehicle categories DataTable (system_admin only). Server-side
 * processing wired to /api/v1/vehicle-categories — same pattern as the
 * asset-category list (see datatables-server-side.js). Delete is blocked
 * by the API while a category is in use (vehicles / drivers / assets);
 * the row's delete button is disabled up front for those, and any 422 the
 * server still returns is shown as-is.
 */
'use strict';

$(function () {
  const t = window.vehicleCategoryListTranslations || {};
  const dt = $('.datatables-vehicle-categories');
  if (!dt.length) return;

  // Names are user-entered and this render path builds raw HTML —
  // DataTables inserts via .html(), so escape explicitly.
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusBox = document.getElementById('vehicle-category-list-status');
  const errorBox = document.getElementById('vehicle-category-list-error');
  const params = new URLSearchParams(window.location.search);
  if (params.get('created') || params.get('saved')) {
    statusBox.textContent = params.get('created') ? t.created : t.saved;
    statusBox.classList.remove('d-none');
  }

  let table;
  let pendingDeleteId = null;

  const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));

  $(document).on('click', '.delete-category', function () {
    pendingDeleteId = $(this).data('id');
    document.getElementById('delete-category-name').textContent = $(this).data('name');
    modal.show();
  });

  document.getElementById('delete-category-confirm').addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    window.axios
      .delete('/api/v1/vehicle-categories/' + pendingDeleteId)
      .then(function () {
        modal.hide();
        errorBox.classList.add('d-none');
        statusBox.textContent = t.deleted;
        statusBox.classList.remove('d-none');
        table.ajax.reload(null, false);
      })
      .catch(function (error) {
        modal.hide();
        statusBox.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      })
      .finally(function () {
        btn.disabled = false;
      });
  });

  // No dropdown filters on this list (search only) — kept as one function so the
  // table's own requests and the Excel/PDF export read the same params, like the other lists.
  const listParams = () => ({});

  table = dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/vehicle-categories', listParams),
    columns: [
      { data: 'id' },
      { data: 'name_en' },
      { data: 'name_ar' },
      { data: 'vehicles_count' },
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
        responsivePriority: 3,
        render: (d, type, full) => '<span class="fw-medium">' + escapeHtml(full.name_en) + '</span>'
      },
      {
        targets: 2,
        render: (d, type, full) => '<span class="fw-medium">' + escapeHtml(full.name_ar) + '</span>'
      },
      { targets: 3, orderable: true, render: (d) => d || 0 },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (d, type, full) {
          const editUrl = (t.edit_url_base || '/app/vehicle-category/edit') + '/' + full.id;
          const edit =
            '<a href="' + editUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            escapeHtml(t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>';

          const del = full.is_default || full.in_use
            ? '<span class="btn btn-icon btn-text-secondary rounded-pill disabled" title="' + escapeHtml(full.is_default ? t.primary_hint : t.in_use_hint) +
              '"><i class="ti ti-trash ti-md opacity-50"></i></span>'
            : '<button type="button" class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete-category" data-id="' +
              full.id + '" data-name="' + escapeHtml(full.name) + '" title="' + escapeHtml(t.delete || 'Delete') +
              '"><i class="ti ti-trash ti-md"></i></button>';

          return edit + del;
        }
      }
    ],
    order: [[0, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search || 'Search',
      emptyTable: t.none_found || 'No categories found.',
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
            action: (e, dt) => window.gcmExport('/api/v1/vehicle-categories/export', 'xlsx', dt, listParams)
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/vehicle-categories/export', 'pdf', dt, listParams)
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_category || 'Add category') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_category_url || '/app/vehicle-category/add')
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => (t.edit || 'Details') + ' — ' + row.data().name_en
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
    }
  });
});
