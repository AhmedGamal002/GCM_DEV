/**
 * Asset capacity categories DataTable (FRD §1.7.2). Server-side
 * processing wired to /api/v1/asset-capacity-categories — same pattern as
 * the assets / vehicles / users lists (see datatables-server-side.js).
 * The detail link goes straight to the edit page; there is no separate
 * details page.
 */
'use strict';

$(function () {
  const t = window.assetCategoryListTranslations || {};
  const dt = $('.datatables-asset-categories');
  if (!dt.length) return;

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const appliesLabels = {
    container: t.container || 'Containers',
    tank: t.tank || 'Tanks',
    both: t.both || 'All'
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('asset-category-list-status');
    box.textContent = t.created || 'Category created successfully.';
    box.classList.remove('d-none');
  }

  let currentType = '';

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/asset-capacity-categories', () => ({
      applies_to: currentType || undefined
    })),
    columns: [
      { data: 'id' },
      { data: 'name' },
      { data: 'applies_to' },
      { data: 'capacity_cbm' },
      { data: 'capacity_ton' },
      { data: 'assets_count' },
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
        render: (d, type, full) => '<span class="fw-medium">' + escapeHtml(full.name) + '</span>'
      },
      {
        targets: 2,
        render: (d, type, full) => (type === 'display' ? escapeHtml(appliesLabels[full.applies_to] || full.applies_to) : full.applies_to)
      },
      { targets: 5, orderable: true, render: (d) => d || 0 },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (d, type, full) {
          const editUrl = (t.edit_url_base || '/app/asset-category/edit') + '/' + full.id;
          return (
            '<a href="' + editUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            (t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>'
          );
        }
      }
    ],
    order: [[1, 'asc']],
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
            action: () => window.location.assign('/api/v1/asset-capacity-categories/export?format=xlsx')
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: () => window.location.assign('/api/v1/asset-capacity-categories/export?format=pdf')
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_category || 'Add category') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_category_url || '/app/asset-category/add')
      }
    ],
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => (t.edit || 'Details') + ' — ' + row.data().name
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
      const select = $(
        '<select class="form-select"><option value="">' + (t.all_types || 'All types') + '</option></select>'
      )
        .appendTo('.category_type')
        .on('change', function () {
          currentType = $(this).val();
          api.draw();
        });
      ['container', 'tank', 'both'].forEach((v) => {
        select.append('<option value="' + v + '">' + (appliesLabels[v] || v) + '</option>');
      });
    }
  });
});
