/**
 * Purchase Orders DataTable (FRD V01.14 §1.13) — the same server-side setup as
 * app-project-list.js, wired to /api/v1/purchase-orders. Columns follow the FRD
 * list spec: ID / client company / project / duration / days left / items status /
 * account status / details. Everything shown (the days left, the items status and
 * their labels) is computed and translated by the server (PurchaseOrderStatus) —
 * this file only paints it. It also fills the five stat cards from
 * /api/v1/purchase-orders/stats. Filters: company, project, time range, items
 * status, account status. Add / Edit are for the System Admin only (the Blade
 * shell passes `can_manage`); export for the staff (`can_export`) — the API's
 * policy remains the real guard.
 */
'use strict';

$(function () {
  const t = window.poListTranslations || {};
  const dt = $('.datatables-purchase-orders');
  const canManage = !!t.can_manage;

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const accountStatus = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };
  const itemStatus = {
    valid: { title: t.valid || 'In force', class: 'bg-label-success' },
    near_exhaustion: { title: t.near_exhaustion || 'About to run out', class: 'bg-label-warning' },
    exhausted: { title: t.exhausted || 'Fully exhausted', class: 'bg-label-danger' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('po-list-status');
    box.textContent = t.created || 'Purchase order created successfully.';
    box.classList.remove('d-none');
  }

  // Stat cards. The money total is shown with its currency under the figure.
  window.axios
    .get('/api/v1/purchase-orders/stats')
    .then(function (response) {
      const data = response.data.data || {};
      Object.entries(data).forEach(([key, val]) => {
        const el = document.querySelector(`[data-stat="${key}"]`);
        if (!el) return;
        el.textContent = key === 'cost_items_total' ? Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : val;
      });
      const cur = document.querySelector('[data-stat-currency]');
      if (cur && data.currency) cur.textContent = data.currency;
    })
    .catch(() => {});

  if (!dt.length) {
    return;
  }

  let currentCompany = '';
  let currentProject = '';
  let currentItemStatus = '';
  let currentAccountStatus = '';
  const dateFrom = document.getElementById('po-date-from');
  const dateTo = document.getElementById('po-date-to');

  // Filters currently applied to the list — the table's own requests AND the
  // Excel/PDF export read this, so an export always matches what is on screen.
  const listParams = () => ({
    company_id: currentCompany || undefined,
    project_id: currentProject || undefined,
    status: currentItemStatus || undefined,
    operational_status: currentAccountStatus || undefined,
    date_from: (dateFrom && dateFrom.value) || undefined,
    date_to: (dateTo && dateTo.value) || undefined
  });

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
          action: (e, dt) => window.gcmExport('/api/v1/purchase-orders/export', 'xlsx', dt, listParams)
        },
        {
          text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
          className: 'dropdown-item',
          action: (e, dt) => window.gcmExport('/api/v1/purchase-orders/export', 'pdf', dt, listParams)
        }
      ]
    });
  }

  if (canManage) {
    buttons.push({
      text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_po || 'Add Purchase Order') + '</span>',
      className: 'add-new btn btn-primary waves-effect waves-light',
      action: () => window.location.assign(t.add_po_url || '/app/po/add')
    });
  }

  dt.DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/purchase-orders', listParams, t.no_permission),
    columns: [
      { data: 'id' },
      { data: 'number' },
      { data: 'company' },
      { data: 'project' },
      { data: 'starts_on' },
      { data: 'ends_on' },
      { data: 'status' },
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
        render: (data, type, full) => '<span class="fw-medium" dir="ltr">' + escapeHtml(full.number) + '</span>'
      },
      {
        targets: 2,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.company && full.company.name)
      },
      {
        targets: 3,
        orderable: false,
        render: (data, type, full) => escapeHtml(full.project && full.project.name)
      },
      {
        // "المدى الزمني": from / to — sorted by its start date.
        targets: 4,
        render: (data, type, full) =>
          type === 'display'
            ? '<span dir="ltr">' + escapeHtml(full.starts_on) + '</span> → <span dir="ltr">' + escapeHtml(full.ends_on) + '</span>'
            : full.starts_on
      },
      {
        // "عدد الأيام المتبقية" — the label (and which count it shows) comes from the server; sorted by the end date.
        targets: 5,
        render: function (data, type, full) {
          if (type !== 'display') return full.ends_on;
          const tone = full.timeline === 'ended' ? 'text-danger' : full.timeline === 'not_started' ? 'text-info' : '';
          return '<span class="' + tone + '">' + escapeHtml(full.days_label) + '</span>';
        }
      },
      {
        targets: 6,
        render: function (data, type, full) {
          if (type !== 'display') return full.status;
          const s = itemStatus[full.status] || { title: full.status_label, class: 'bg-label-secondary' };
          return '<span class="badge ' + s.class + '">' + escapeHtml(full.status_label || s.title) + '</span>';
        }
      },
      {
        targets: 7,
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
        render: function (data, type, full) {
          const viewUrl = (t.view_url_base || '/app/po/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/po/edit') + '/' + full.id;
          return (
            '<div class="d-flex align-items-center">' +
            '<a href="' + viewUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
            (t.view || 'View') + '"><i class="ti ti-eye ti-md"></i></a>' +
            (canManage
              ? '<a href="' + editUrl + '" class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill" title="' +
                (t.edit || 'Edit') + '"><i class="ti ti-edit ti-md"></i></a>'
              : '') +
            '</div>'
          );
        }
      }
    ],
    order: [[4, 'desc']],
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
    responsive: {
      details: {
        display: $.fn.dataTable.Responsive.display.modal({
          header: (row) => (t.view || 'Details') + ' — ' + row.data().number
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

      const select = (holder, allLabel, onChange) =>
        $('<select class="form-select"><option value="">' + allLabel + '</option></select>').appendTo(holder).on('change', function () {
          onChange($(this).val());
          api.draw();
        });

      // Company and project lists belong to the staff; a client manager has neither (their POs are already their own).
      if (t.can_filter_lookups) {
        const projectSelect = select('.po_project', t.all_projects || 'All projects', (v) => (currentProject = v));

        const fillProjects = (companyId) => {
          projectSelect.find('option:not(:first)').remove();
          currentProject = '';
          projectSelect.val('');
          // Every project of the chosen company (or all of them), not just the first page.
          window
            .gcmFetchAll('/api/v1/projects', Object.assign({ sort_by: 'name' }, companyId ? { company_id: companyId } : {}))
            .then((projects) => projects.forEach((p) => projectSelect.append($('<option>').val(p.id).text(p.name))))
            .catch(() => {});
        };

        const companySelect = select('.po_company', t.all_companies || 'All companies', (v) => {
          currentCompany = v;
          fillProjects(v);
        });

        window
          .gcmFetchAll('/api/v1/companies', { sort_by: 'name' })
          .then((companies) => companies.forEach((c) => companySelect.append($('<option>').val(c.id).text(c.name))))
          .catch(() => {});
        fillProjects('');
      } else {
        $('.po_company, .po_project').remove();
      }

      const items = select('.po_item_status', t.all_item_statuses || 'All items statuses', (v) => (currentItemStatus = v));
      ['valid', 'near_exhaustion', 'exhausted'].forEach((k) => items.append('<option value="' + k + '">' + itemStatus[k].title + '</option>'));

      const accounts = select('.po_account_status', t.all_account_statuses || 'All statuses', (v) => (currentAccountStatus = v));
      ['active', 'deactivated'].forEach((k) => accounts.append('<option value="' + k + '">' + accountStatus[k].title + '</option>'));

      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));
    }
  });
});
