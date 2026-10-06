/**
 * Shared bits for the dated-document tables on the driver and vehicle pages: the remaining-validity bar,
 * the status tag, and the DataTables options both pages use.
 *
 * `expiry` is the object the API puts next to every `valid_to` (see ExpiryWarning::describe()):
 * { status: 'ok' | 'warning' | 'expired', status_label, percent, days_remaining, label } — or null when the
 * document has no end date. Green and full while there is plenty of time; once the days left reach the
 * threshold set in General Settings it turns yellow ("need review") and shrinks day by day; empty and red
 * once it has expired. The labels already come translated from the server, so there is nothing to translate here.
 */
'use strict';

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value == null ? '' : value;
  return div.innerHTML;
}

const BAR_COLOR = { ok: 'success', warning: 'warning', expired: 'danger' };
const TAG_COLOR = { ok: 'success', warning: 'warning', expired: 'danger' };

/** The bar with the days left written under it. */
window.gcmExpiryBar = function (expiry) {
  if (!expiry) return '<span class="text-muted">—</span>';

  const percent = Number(expiry.percent);
  const labelClass = expiry.status === 'expired' ? 'text-danger' : 'text-muted';

  return (
    '<div class="gcm-expiry" style="min-width: 120px">' +
    '<div class="progress" style="height: 8px" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + percent + '">' +
    '<div class="progress-bar bg-' + (BAR_COLOR[expiry.status] || 'secondary') + '" style="width: ' + percent + '%"></div>' +
    '</div>' +
    '<small class="d-block mt-1 text-nowrap ' + labelClass + '">' + escapeHtml(expiry.label) + '</small>' +
    '</div>'
  );
};

/** The Valid / Need review / Expired tag. */
window.gcmExpiryTag = function (expiry) {
  if (!expiry) return '<span class="text-muted">—</span>';

  return '<span class="badge bg-label-' + (TAG_COLOR[expiry.status] || 'secondary') + '">' + escapeHtml(expiry.status_label) + '</span>';
};

/**
 * The tables are initialised while their cards are still hidden (the page unhides everything once it has
 * its data), so DataTables measured them at zero width. Call this after the cards are visible so the
 * responsive plugin folds columns that don't fit instead of leaving a horizontal scrollbar.
 */
window.gcmRecalcDocTables = function () {
  const jq = window.jQuery || window.$;
  if (!jq || !jq.fn || !jq.fn.dataTable) return;

  const api = jq.fn.dataTable.tables({ visible: true, api: true });
  api.columns.adjust();
  if (api.responsive) api.responsive.recalc();
};

/**
 * DataTables options for the small tables inside a details page (documents, permits): search + paging on
 * rows that are already loaded. Column layout: [..., Valid to, Time left (bar), Status, Attachment] — the bar
 * and the attachment are not sortable. Wrap the table in `.card-datatable` so the search box and the
 * "Showing x to y of z" footer get the same padding as on the list pages.
 */
window.gcmDocTableOptions = function (t, emptyMessage) {
  return {
    responsive: true,
    searching: true,
    lengthChange: false,
    pageLength: 10,
    language: {
      search: '',
      searchPlaceholder: t.search || 'Search',
      emptyTable: emptyMessage,
      zeroRecords: emptyMessage,
      info: t.info || 'Showing _START_ to _END_ of _TOTAL_ entries',
      infoEmpty: t.info_empty || 'Showing 0 to 0 of 0 entries',
      paginate: {
        next: '<i class="ti ti-chevron-right ti-sm"></i>',
        previous: '<i class="ti ti-chevron-left ti-sm"></i>'
      }
    },
    // The rows get their own side padding (the wrapper has none), so the search box and the
    // "Showing x to y of z" footer don't sit on the card edge.
    dom: '<"row mx-0 px-4 pt-4 pb-2"<"col-12 px-0"f>>t<"row mx-0 px-4 py-4"<"col-sm-12 col-md-6 px-0"i><"col-sm-12 col-md-6 px-0"p>>',
    // When the card is too narrow for every column the responsive plugin folds the lowest-priority ones into
    // the row's details: the number goes first, then the attachment; the name, the tag and the bar stay.
    columnDefs: [
      { targets: [3, -1], orderable: false },
      { targets: 0, responsivePriority: 1 },
      { targets: 4, responsivePriority: 2 },
      { targets: 3, responsivePriority: 3 },
      { targets: 2, responsivePriority: 4 },
      { targets: 5, responsivePriority: 5 },
      { targets: 1, responsivePriority: 6 }
    ]
  };
};
