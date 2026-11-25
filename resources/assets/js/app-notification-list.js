/**
 * The notifications page (FRD V01.14: "صفحة الاشعارات", part 8.2) — the signed-in user's own notifications from
 * /api/v1/notifications: date & time, type, details; filters by type and time range, the search box (trip code,
 * contract number, type), newest first. A notification with a link makes its details a link; an unread one is
 * marked "New". All text is set as text (the sentence is written by the server from the notification's data).
 */
'use strict';

$(function () {
  const t = window.notificationListTranslations || {};
  const table = $('.datatables-notifications');
  if (!table.length) return;

  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();
  const filters = { type: '' };
  const dateFrom = document.getElementById('notification-date-from');
  const dateTo = document.getElementById('notification-date-to');
  const listParams = () => ({ type: filters.type || undefined, date_from: (dateFrom && dateFrom.value) || undefined, date_to: (dateTo && dateTo.value) || undefined });

  const dt = table.DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    searchDelay: 500,
    ajax: window.gcmServerSideAjax('/api/v1/notifications', listParams, t.no_permission),
    columns: [{ data: 'created_at' }, { data: 'type_label' }, { data: 'detail' }],
    columnDefs: [
      {
        targets: 0,
        render: (data, type, full) =>
          '<span dir="ltr">' + escapeHtml(full.created_at ? moment(full.created_at).format('YYYY-MM-DD hh:mm A') : '') + '</span>' +
          (full.read ? '' : ' <span class="badge bg-label-primary ms-1">' + escapeHtml(t.new_label) + '</span>')
      },
      { targets: 1, render: (data, type, full) => '<span class="fw-medium">' + escapeHtml(full.type_label) + '</span>' },
      {
        targets: 2,
        render: (data, type, full) =>
          full.url ? '<a href="' + escapeHtml(full.url) + '">' + escapeHtml(full.detail) + '</a>' : escapeHtml(full.detail)
      }
    ],
    language: {
      sLengthMenu: '_MENU_',
      search: '',
      searchPlaceholder: t.search || 'Search notifications',
      emptyTable: t.no_notifications || 'No notifications yet.',
      info: t.info,
      infoEmpty: t.info_empty,
      paginate: { next: '<i class="ti ti-chevron-right ti-sm"></i>', previous: '<i class="ti ti-chevron-left ti-sm"></i>' }
    },
    dom:
      '<"row"<"col-md-2"<"ms-n2"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"f>>>t' +
      '<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
    initComplete: function () {
      const api = this.api();
      const select = $('<select class="form-select"><option value="">' + escapeHtml(t.all_types) + '</option></select>')
        .appendTo('.notification_type')
        .on('change', function () {
          filters.type = $(this).val();
          api.draw();
        });
      Object.entries(t.types || {}).forEach(([value, label]) => select.append($('<option>').val(value).text(label)));
      [dateFrom, dateTo].forEach((input) => input && input.addEventListener('change', () => api.draw()));
    }
  });

  // "Mark all as read": the rows lose their "New" tag and the bell its count.
  document.getElementById('notifications-read-all').addEventListener('click', function () {
    window.gcmBusy.start();
    window.axios
      .post('/api/v1/notifications/read')
      .then(() => {
        window.gcmBusy.stop();
        dt.draw(false);
        if (window.gcmNotifications) window.gcmNotifications.refresh();
      })
      .catch((error) => {
        window.gcmBusy.stop();
        window.alert(window.gcmErrorMessage(error));
      });
  });
});
