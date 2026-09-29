/**
 * window.gcmFetchAll(url, params): loads EVERY row of a paginated list
 * endpoint (`{data, meta: {last_page}}`), following the pages. For
 * reference data that fills a <select> (a company / project / asset
 * picker) — a fixed `per_page=200` would silently drop rows past it, the
 * same failure the list pages avoid with server-side DataTables.
 * Resolves to the flat array of rows.
 */
'use strict';

window.gcmFetchAll = async function (url, params) {
  const perPage = 100;
  const rows = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await window.axios.get(url, { params: Object.assign({}, params, { per_page: perPage, page }) });
    rows.push(...response.data.data);
    lastPage = (response.data.meta && response.data.meta.last_page) || 1;
    page += 1;
  } while (page <= lastPage);

  return rows;
};
