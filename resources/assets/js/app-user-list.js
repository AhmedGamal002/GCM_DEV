/**
 * Users DataTable — restores the original template's DataTables-based
 * layout (search + export together in the same row, role/status filters
 * built from the loaded column data, "Processing" indicator while ajax
 * loads) instead of a hand-rolled table, wired to the real
 * /api/v1/users endpoint. Columns match the FRD's list-table spec:
 * ID / User / Affiliation / Entity / Role / Status / Actions. The
 * "Actions" column is a single link to the dedicated account-details
 * page (FRD: "رابط لصفحة تفاصيل هذا الحساب") — editing and status
 * changes happen there, not inline on this list.
 */
'use strict';

$(function () {
  const t = window.userListTranslations || {};
  const dtUserTable = $('.datatables-users');

  // name/email are user-controlled (any tenant role can set their own
  // name via the self-service profile) and this render path builds raw
  // HTML strings, so it must be escaped explicitly — DataTables inserts
  // a render callback's return value via .html(), not as text.
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const statusObj = {
    active: { title: t.active || 'Active', class: 'bg-label-success' },
    on_vacation: { title: t.on_vacation || 'On Vacation', class: 'bg-label-warning' },
    deactivated: { title: t.deactivated || 'Deactivated', class: 'bg-label-secondary' }
  };

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('user-list-status');
    box.textContent = t.created || 'User created successfully.';
    box.classList.remove('d-none');
  }

  if (!dtUserTable.length) {
    return;
  }

  dtUserTable.DataTable({
    processing: true,
    ajax: {
      url: '/api/v1/users?per_page=1000',
      dataSrc: 'data'
    },
    columns: [
      { data: 'id' },
      { data: 'id' },
      { data: 'code' },
      { data: 'name' },
      { data: 'affiliation' },
      { data: 'entity_name' },
      { data: 'roles' },
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
        // Name + email, same visual pattern as the template's other user tables
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
        render: (data, type, full) => (full.affiliation === 'gcm' ? 'GCM' : full.affiliation)
      },
      {
        targets: 5,
        render: (data, type, full) => full.entity_name || ''
      },
      {
        targets: 6,
        render: (data, type, full) => full.roles.join(', ')
      },
      {
        targets: 7,
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
          const viewUrl = (t.view_url_base || '/app/user/view') + '/' + full.id;
          const editUrl = (t.edit_url_base || '/app/user/edit') + '/' + full.id;
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
      searchPlaceholder: t.search_user || 'Search User',
      emptyTable: t.no_users_found || 'No users found.',
      info: t.info || 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: t.info_empty || 'Showing 0 to 0 of 0 entries',
      paginate: {
        next: '<i class="ti ti-chevron-right ti-sm"></i>',
        previous: '<i class="ti ti-chevron-left ti-sm"></i>'
      }
    },
    // Search + export live in the same row as the original template's
    // layout (dt-action-buttons wraps both f=search and B=buttons).
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
            action: () => window.location.assign('/api/v1/users/export?format=xlsx')
          },
          {
            text: '<i class="ti ti-file-code-2 me-2"></i>Pdf',
            className: 'dropdown-item',
            action: () => window.location.assign('/api/v1/users/export?format=pdf')
          }
        ]
      },
      {
        text: '<i class="ti ti-plus me-0 me-sm-1 ti-xs"></i><span class="d-none d-sm-inline-block">' + (t.add_user || 'Add User') + '</span>',
        className: 'add-new btn btn-primary waves-effect waves-light',
        action: () => window.location.assign(t.add_user_url || '/app/user/add')
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
      // Role filter, built from whatever roles are actually present in
      // the loaded data — same mechanism the original template used.
      this.api()
        .columns(6)
        .every(function () {
          const column = this;
          const select = $(
            '<select class="form-select text-capitalize"><option value="">' + (t.all_roles || 'All roles') + '</option></select>'
          )
            .appendTo('.user_role')
            .on('change', function () {
              const val = $.fn.dataTable.util.escapeRegex($(this).val());
              column.search(val ? val : '', true, false).draw();
            });

          const seen = new Set();
          column
            .data()
            .each(function (roles) {
              roles.forEach((role) => seen.add(role));
            });
          Array.from(seen).sort().forEach((role) => select.append('<option value="' + role + '">' + role + '</option>'));
        });

      // Status filter
      this.api()
        .columns(7)
        .every(function () {
          const column = this;
          const select = $(
            '<select class="form-select"><option value="">' + (t.all_statuses || 'All statuses') + '</option></select>'
          )
            .appendTo('.user_status')
            .on('change', function () {
              const val = $.fn.dataTable.util.escapeRegex($(this).val());
              column.search(val ? '^' + val + '$' : '', true, false).draw();
            });

          ['active', 'on_vacation', 'deactivated'].forEach((status) => {
            select.append('<option value="' + status + '">' + (statusObj[status].title) + '</option>');
          });
        });
    }
  });
});
