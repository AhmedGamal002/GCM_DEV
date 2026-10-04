/**
 * Main Services DataTable — the same server-side setup as
 * app-facility-list.js, wired to /api/v1/main-services. FRD §1.9.1 columns:
 * the main service's name (link to its details) and its sub-services (each a
 * link to its own details), plus the type and status. Search also matches
 * sub-service names (the column is on screen).
 */
'use strict';

$(function () {
  const t = window.mainServiceListTranslations || {};
  const dt = $('.datatables-main-services');

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  const typeObj = {
    non_hazardous: { title: t.non_hazardous || 'Non-Hazardous waste', class: 'bg-label-primary' },
    hazardous: { title: t.hazardous || 'Hazardous waste', class: 'bg-label-danger' },
    supplying: { title: t.supplying || 'Supplying', class: 'bg-label-info' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('main-service-list-status');
    box.textContent = t.created || 'Main service created successfully.';
    box.classList.remove('d-none');
  }

  if (!dt.length) {
    return;
  }

  const SUB_BADGES_SHOWN = 3;

  const toggleIcon = (expanded) => '<i class="ti ' + (expanded ? 'ti-minus' : 'ti-plus') + ' ti-xs"></i>';

  // "+" / "−" icon button — expands the folded sub-service badges in place.
  // The words ("Show more (+N more)" / "Show less") live in the tooltip and
  // aria-label. Delegated on document so it also works inside the responsive
  // details modal.
  $(document).on('click', '.js-subs-toggle', function () {
    const btn = $(this);
    const extras = btn.siblings('[data-sub-extra]');
    const expanding = extras.first().hasClass('d-none');
    extras.toggleClass('d-none', !expanding);
    btn.parent().toggleClass('flex-wrap', expanding).toggleClass('flex-nowrap', !expanding);
    const label = expanding ? t.show_less || 'Show less' : btn.data('more-label');
    btn.html(toggleIcon(expanding)).attr({ title: label, 'aria-label': label, 'aria-expanded': expanding });
  });

  const canManage = !!t.can_manage;
  const viewBase = t.view_url_base || '/app/main-service/view';
  const editBase = t.edit_url_base || '/app/main-service/edit';
  const subViewBase = t.sub_view_url_base || '/app/sub-service/view';
  let currentType = '';
  let currentStatus = '';

  // Filters currently applied — the table's requests AND the Excel/PDF
  // export read this, so an export always matches what is on screen.
  const listParams = () => ({
    service_type: currentType || undefined,
    operational_status: currentStatus || undefined
  });

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/main-services', listParams),
    columns: [
      { data: 'id' },
      { data: 'name' },
      { data: 'service_type' },
      { data: 'sub_services' },
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
        responsivePriority: 1,
        render: (data, type, full) =>
          '<a href="' + viewBase + '/' + full.id + '" class="fw-medium">' + escapeHtml(full.name) + '</a>'
      },
      {
        targets: 2,
        render: function (data, type, full) {
          if (type !== 'display') return full.service_type;
          const s = typeObj[full.service_type] || { title: full.service_type, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: 3,
        orderable: false,
        render: function (data, type, full) {
          const subs = full.sub_services || [];
          if (!subs.length) return '<small class="text-muted">' + escapeHtml(t.no_sub_services || 'No sub-services yet') + '</small>';

          const badge = (s, hidden) =>
            '<a href="' + subViewBase + '/' + s.id + '" class="badge ' +
            (s.operational_status === 'active' ? 'bg-label-primary' : 'bg-label-secondary') +
            (hidden ? ' d-none" data-sub-extra' : '"') +
            '>' + escapeHtml(s.name) + '</a>';

          // A main service can group many sub-services — show the first few
          // and fold the rest behind a "+N more" toggle so rows stay one line tall.
          const extra = subs.length - SUB_BADGES_SHOWN;
          return (
            // Collapsed: one line (nowrap) so every row keeps the same height;
            // the toggle switches to wrapping once the rest is expanded.
            '<div class="d-flex ' + (extra > 0 ? 'flex-nowrap' : 'flex-wrap') + ' align-items-center gap-1">' +
            subs.map((s, i) => badge(s, i >= SUB_BADGES_SHOWN)).join('') +
            // A small round icon button — deliberately NOT a badge, so it can't
            // be mistaken for a sub-service or a status. The hidden count is in
            // the tooltip.
            (extra > 0
              ? (() => {
                  const label = escapeHtml((t.show_more || 'Show more') + ' (' + extra + ')');
                  return (
                    '<button type="button" class="btn btn-icon btn-xs rounded-pill btn-dark flex-shrink-0 ms-1 js-subs-toggle" aria-expanded="false" title="' +
                    label + '" aria-label="' + label + '" data-more-label="' + label + '">' + toggleIcon(false) + '</button>'
                  );
                })()
              : '') +
            '</div>'
          );
        }
      },
      {
        targets: 4,
        render: function (data, type, full) {
          if (type !== 'display') return full.operational_status;
          const s = statusObj[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
        }
      },
      {
        targets: -1,
        title: t.actions || 'Actions',
        searchable: false,
        orderable: false,
        render: function (data, type, full) {
          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + viewBase + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            escapeHtml(t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
            (canManage
              ? '<a href="' + editBase + '/' + full.id + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
                escapeHtml(t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>'
              : '') +
            '</div>'
          );
        }
      }
    ],
    order: [[1, 'asc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_main_service || 'Search service',
      emptyTable: t.no_main_services_found || 'No main services found.',
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
            action: (e, dt) => window.gcmExport('/api/v1/main-services/export', 'xlsx', dt, listParams)
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: (e, dt) => window.gcmExport('/api/v1/main-services/export', 'pdf', dt, listParams)
          }
        ]
      },
      ...(canManage
        ? [
            {
              text:
                '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' +
                escapeHtml(t.add_main_service || 'Create New Main Service') +
                '</span>',
              className: 'add-new btn btn-primary waves-effect waves-light',
              action: () => window.location.assign(t.add_url || '/app/main-service/add')
            }
          ]
        : [])
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

      const typeSelect = $('<select class="form-select"><option value="">' + escapeHtml(t.all_types || 'All types') + '</option></select>')
        .appendTo('.main_service_type')
        .on('change', function () {
          currentType = $(this).val();
          api.draw();
        });
      Object.entries(typeObj).forEach(([value, s]) => {
        typeSelect.append('<option value="' + value + '">' + escapeHtml(s.title) + '</option>');
      });

      const statusSelect = $('<select class="form-select"><option value="">' + escapeHtml(t.all_statuses || 'All statuses') + '</option></select>')
        .appendTo('.main_service_status')
        .on('change', function () {
          currentStatus = $(this).val();
          api.draw();
        });
      ['active', 'deactivated'].forEach((status) => {
        statusSelect.append('<option value="' + status + '">' + escapeHtml(statusObj[status].title) + '</option>');
      });
    }
  });
});
