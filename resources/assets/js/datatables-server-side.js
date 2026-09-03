'use strict';

/**
 * Adapts DataTables' `serverSide` protocol to this app's existing
 * paginated API shape (`per_page`/`page`/`search`/`sort_by`/`sort_dir`
 * query params, `{data, meta: {total}}` response) instead of DataTables'
 * own `draw`/`start`/`length` request format and `recordsTotal`/
 * `recordsFiltered` response shape — so none of the controllers need to
 * speak DataTables' wire format directly, and the same JSON keeps
 * working for Postman/any other API consumer.
 *
 * Exists because every list page (Users/Drivers/Vehicles) used to fetch
 * up to `per_page=1000` rows ONCE and let DataTables paginate/search/
 * sort/filter entirely client-side — fine for a demo, a real problem at
 * scale: past 1000 rows (a real tenant can have thousands of employees)
 * the rest simply never loaded — not just slow, actually invisible,
 * unsearchable, absent from filter dropdowns and totals. See
 * ARCHITECTURE.md's performance section for the full writeup.
 *
 * Usage:
 *   ajax: window.gcmServerSideAjax('/api/v1/users', () => ({ status: currentStatus, role: currentRole }), t.no_permission),
 *   serverSide: true,
 *   searchDelay: 500,
 *
 * `getExtraParams` is called on every request — return whatever extra
 * filter params the page's own (now server-driven, not data-driven)
 * filter dropdowns currently hold. Return `undefined`/omit a key to
 * leave that filter off.
 *
 * `noPermissionMessage` (optional, translated string) is shown in place
 * of the table's normal "no data" text specifically on a 403 response.
 * Menu items are now role-filtered (see MenuComposer) so a wrong-role
 * user shouldn't normally land on these pages at all, but the page
 * itself has no server-side gate (by design — see UserAccountController's
 * docblock) and is reachable by a direct URL; without this, a 403 used to
 * render exactly like "this list happens to be empty", which is
 * indistinguishable from an actual permissions problem.
 */
window.gcmServerSideAjax = function (url, getExtraParams, noPermissionMessage) {
  return function (requestData, callback, settings) {
    const params = {
      per_page: requestData.length > 0 ? requestData.length : 1000,
      page: requestData.length > 0 ? Math.floor(requestData.start / requestData.length) + 1 : 1
    };

    if (requestData.search && requestData.search.value) {
      params.search = requestData.search.value;
    }

    if (requestData.order && requestData.order.length) {
      const orderedColumn = requestData.columns[requestData.order[0].column];
      if (orderedColumn && orderedColumn.orderable !== false && typeof orderedColumn.data === 'string') {
        params.sort_by = orderedColumn.data;
        params.sort_dir = requestData.order[0].dir;
      }
    }

    Object.assign(params, (getExtraParams && getExtraParams()) || {});

    window.axios
      .get(url, { params })
      .then(function (response) {
        callback({
          draw: requestData.draw,
          // The backend only ever runs one filtered query — there's no
          // separate "total before any filter" count kept around (that
          // would be a second query per request purely for a cosmetic
          // DataTables number), so recordsTotal/recordsFiltered are the
          // same value here. DataTables only uses recordsTotal to decide
          // whether "filtered from _MAX_ total entries" text applies;
          // reporting the same number both ways just means that extra
          // clause never renders — the "Showing X to Y of Z" line the
          // template actually uses is driven by recordsFiltered, so
          // stays fully correct either way.
          recordsTotal: response.data.meta.total,
          recordsFiltered: response.data.meta.total,
          data: response.data.data
        });
      })
      .catch(function (error) {
        if (noPermissionMessage && error.response && error.response.status === 403 && settings && settings.oLanguage) {
          settings.oLanguage.sEmptyTable = noPermissionMessage;
          settings.oLanguage.sZeroRecords = noPermissionMessage;
        }
        callback({ draw: requestData.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
      });
  };
};
