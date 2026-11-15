/**
 * The "Contracts" section of a client company's page and of a project's page (FRD V01.14 §1.11.3 "قسم التعاقدات
 * التابعة للعميل" and §1.12.3 "قسم التعاقدات التابعة لهذا المشروع"): a server-side DataTable of that company's or
 * project's purchase orders from GET /api/v1/purchase-orders, narrowed by `params` (company_id / project_id) — the
 * same list, filters and export as the Purchase Orders page, so what is shown is what the API's policy lets this
 * role see. Everything displayed (days left, items status and their labels) is computed and translated by the
 * server; this only paints it.
 *
 * Both view scripts call initRelatedPoTable() once the page's own id is known. The Blade partial that holds the
 * table is only rendered for a role that may view POs (Data Entry has no access at all), so the call does nothing
 * when the table isn't there.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob doesn't pick it up as its own entry.
 */
'use strict';

/**
 * @param {object}   opts
 * @param {function} opts.params       the filters that narrow the list: () => ({company_id}) or ({project_id})
 * @param {boolean}  opts.showProject  a company's page shows each PO's project; a project's page doesn't
 */
export function initRelatedPoTable(opts) {
  const t = window.poRelatedTranslations || {};
  const dt = window.$('.datatables-related-pos');
  if (!dt.length) return;

  const params = opts.params;
  const showProject = !!opts.showProject;
  const escapeHtml = (value) => window.$('<div>').text(value == null ? '' : value).html();

  const accountStatus = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };
  const itemStatus = {
    valid: { title: t.valid || 'In force', class: 'bg-label-success' },
    near_exhaustion: { title: t.near_exhaustion || 'About to run out', class: 'bg-label-warning' },
    exhausted: { title: t.exhausted || 'Fully exhausted', class: 'bg-label-danger' }
  };

  let currentItemStatus = '';
  const listParams = () => Object.assign({}, params(), { status: currentItemStatus || undefined });

  const buttons = [];

  if (t.can_export) {
    buttons.push({
      extend: 'collection',
      className: 'btn btn-label-secondary dropdown-toggle mx-4 waves-effect waves-light',
      text: '<i class="ti ti-upload me-2 ti-xs"></i>' + (t.export || 'Export'),
      buttons: [
        {
          text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
          className: 'dropdown-item',
          action: (e, table) => window.gcmExport('/api/v1/purchase-orders/export', 'xlsx', table, listParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, table) => window.gcmExport('/api/v1/purchase-orders/export', 'pdf', table, listParams)
        }
      ]
    });
  }

  // "Add Purchase Order" lands on the create form with this company / project already chosen.
  if (t.can_manage) {
    const query = new URLSearchParams();
    Object.entries(params()).forEach(([key, value]) => value && query.set(key, value));
    buttons.push({
      text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_po || 'Add Purchase Order') + '</span>',
      className: 'btn btn-primary waves-effect waves-light',
      action: () => window.location.assign((t.add_po_url || '/app/po/add') + '?' + query.toString())
    });
  }

  // control · number · [project] · duration · days left · items status · account status · details
  const columns = [{ data: 'id' }, { data: 'number' }];
  if (showProject) columns.push({ data: 'project' });
  columns.push({ data: 'starts_on' }, { data: 'ends_on' }, { data: 'status' }, { data: 'operational_status' }, { data: 'id' });
  const at = (name) => columns.findIndex((c, i) => i > 0 && c.data === name);

  const columnDefs = [
    { className: 'control', searchable: false, orderable: false, responsivePriority: 2, targets: 0, render: () => '' },
    {
      targets: 1,
      responsivePriority: 1,
      render: (data, type, full) => '<span class="fw-medium" dir="ltr">' + escapeHtml(full.number) + '</span>'
    },
    {
      targets: at('starts_on'),
      render: (data, type, full) =>
        type === 'display'
          ? '<span dir="ltr">' + escapeHtml(full.starts_on) + '</span> → <span dir="ltr">' + escapeHtml(full.ends_on) + '</span>'
          : full.starts_on
    },
    {
      targets: at('ends_on'),
      render: function (data, type, full) {
        if (type !== 'display') return full.ends_on;
        const tone = full.timeline === 'ended' ? 'text-danger' : full.timeline === 'not_started' ? 'text-info' : '';
        return '<span class="' + tone + '">' + escapeHtml(full.days_label) + '</span>';
      }
    },
    {
      targets: at('status'),
      render: function (data, type, full) {
        if (type !== 'display') return full.status;
        const s = itemStatus[full.status] || { title: full.status_label, class: 'bg-label-secondary' };
        return '<span class="badge ' + s.class + '">' + escapeHtml(full.status_label || s.title) + '</span>';
      }
    },
    {
      targets: at('operational_status'),
      render: function (data, type, full) {
        if (type !== 'display') return full.operational_status;
        const s = accountStatus[full.operational_status] || { title: full.operational_status, class: 'bg-label-secondary' };
        return '<span class="badge ' + s.class + '">' + escapeHtml(s.title) + '</span>';
      }
    },
    {
      targets: -1,
      title: t.actions || 'Actions',
      searchable: false,
      orderable: false,
      render: (data, type, full) =>
        '<a href="' + (t.view_url_base || '/app/po/view') + '/' + full.id +
        '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' + (t.view || 'View') +
        '"><i class="ti ti-eye ti-md"></i></a>'
    }
  ];

  if (showProject) {
    columnDefs.push({ targets: 2, orderable: false, render: (data, type, full) => escapeHtml(full.project && full.project.name) });
  }

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/purchase-orders', listParams, t.no_permission),
    columns: columns,
    columnDefs: columnDefs,
    order: [[at('starts_on'), 'desc']],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search_po || 'Search Purchase Order',
      emptyTable: t.no_pos_found || 'No purchase orders found.',
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
    buttons: buttons,
    responsive: true,
    initComplete: function () {
      const api = this.api();
      const select = window.$('<select class="form-select"><option value="">' + (t.all_item_statuses || 'All items statuses') + '</option></select>')
        .appendTo('.related_po_status')
        .on('change', function () {
          currentItemStatus = window.$(this).val();
          api.draw();
        });
      ['valid', 'near_exhaustion', 'exhausted'].forEach((k) => select.append('<option value="' + k + '">' + itemStatus[k].title + '</option>'));
    }
  });
}
