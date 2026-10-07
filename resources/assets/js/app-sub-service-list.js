/**
 * Sub Services page — FRD V01.14 §1.9.2: an "Accordion With Icon" (open by
 * default, collapsible) with one tab per main service and its sub-services
 * inside, each with a view and an edit icon.
 *
 * Built to stay light with any number of services:
 *  - server-side paginated over the MAIN services (10 per page by default);
 *  - each tab arrives with only its first PAGE of sub-services plus the true
 *    total (GET /api/v1/main-services?subs_limit=10 → `sub_services` +
 *    `sub_services_count`), shows the first few, and a "Show more" button
 *    reveals them 10 at a time — fetching the next page from
 *    GET /api/v1/sub-services?main_service_id=… only when it runs out;
 *  - a tab with many sub-services also links to the main service's details
 *    page, whose table has search, filters and paging.
 * The search box (debounced), its "Search in" selector (all / main services /
 * sub-services), the type filter and the status filter are all applied by the
 * server: by default the search matches a main service's name or any of its
 * sub-services' names, across every page.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.subServiceListTranslations || {};
  const accordion = document.getElementById('subServiceAccordion');
  const loading = document.getElementById('sub-service-list-loading');
  const emptyBox = document.getElementById('sub-service-list-empty');
  const errorBox = document.getElementById('sub-service-list-error');
  const search = document.getElementById('sub-service-search');
  const searchIn = document.getElementById('sub-service-search-in');
  const typeFilter = document.getElementById('sub-service-type-filter');
  const statusFilter = document.getElementById('sub-service-status-filter');
  const perPageSelect = document.getElementById('sub-service-per-page');
  const footer = document.getElementById('sub-service-footer');
  const info = document.getElementById('sub-service-info');
  const pager = document.getElementById('sub-service-pager');

  if (new URLSearchParams(window.location.search).get('created')) {
    const box = document.getElementById('sub-service-list-status');
    box.textContent = t.created;
    box.classList.remove('d-none');
  }

  const typeObj = {
    non_hazardous: { title: t.non_hazardous, icon: 'ti-trash', class: 'bg-label-primary' },
    hazardous: { title: t.hazardous, icon: 'ti-alert-triangle', class: 'bg-label-danger' },
    supplying: { title: t.supplying, icon: 'ti-droplet', class: 'bg-label-info' }
  };
  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const assetLabels = { container: t.container, tank: t.tank };

  // How many sub-service rows a tab shows at first, how many each
  // "Show more" adds (= the page size fetched from the server), and from
  // which total a tab also links to the details page.
  const SUBS_SHOWN = 3;
  const SUBS_STEP = 10;
  const VIEW_ALL_FROM = 20;

  // Filters currently applied — read by every request.
  const state = { page: 1, perPage: Number(perPageSelect.value) || 10, search: '', scope: 'all', type: '', status: '' };

  // "Search in" selector — what the search box looks at.
  const scopes = {
    all: { option: t.search_in_all || 'Search in: All', placeholder: t.placeholder_all || 'Search main or sub-service' },
    main: { option: t.search_in_main || 'Search in: Main services', placeholder: t.placeholder_main || 'Search main service' },
    sub: { option: t.search_in_sub || 'Search in: Sub-services', placeholder: t.placeholder_sub || 'Search sub-service' }
  };
  searchIn.innerHTML = Object.keys(scopes)
    .map((key) => '<option value="' + key + '">' + scopes[key].option + '</option>')
    .join('');
  search.placeholder = scopes.all.placeholder;

  // Filter dropdowns (same options as the main services list).
  typeFilter.innerHTML =
    '<option value="">' + (t.all_types || 'All types') + '</option>' +
    Object.keys(typeObj)
      .map((key) => '<option value="' + key + '">' + typeObj[key].title + '</option>')
      .join('');
  statusFilter.innerHTML =
    '<option value="">' + (t.all_statuses || 'All statuses') + '</option>' +
    Object.keys(statusObj)
      .map((key) => '<option value="' + key + '">' + statusObj[key].title + '</option>')
      .join('');

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  const badge = (obj, key) => {
    const s = obj[key] || { title: key, class: 'bg-label-secondary' };
    return '<span class="badge ' + s.class + '">' + esc(s.title) + '</span>';
  };

  const fill = (template, values) => Object.keys(values).reduce((out, k) => out.replace(':' + k, values[k]), template || '');

  function subRow(sub) {
    const swatch = sub.skip_color_code
      ? '<span class="d-inline-block rounded-circle border me-1" style="width:12px;height:12px;background:' +
        esc(sub.skip_color_hex || 'transparent') + '" title="' + esc(sub.skip_color_label) + '"></span>'
      : '';
    return (
      '<li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2" data-sub-row>' +
      '<div class="d-flex flex-wrap align-items-center gap-2">' +
      swatch +
      '<a href="' + t.view_url_base + '/' + sub.id + '" class="fw-medium">' + esc(sub.name) + '</a>' +
      '<span class="badge bg-label-dark">' + esc(assetLabels[sub.asset_type] || sub.asset_type) + '</span>' +
      (sub.requires_recycle_receipt ? '<span class="badge bg-label-success"><i class="ti ti-receipt ti-xs me-1"></i>' + esc(t.recycle_receipt) + '</span>' : '') +
      badge(statusObj, sub.operational_status) +
      '</div>' +
      '<div class="d-flex align-items-center">' +
      '<a href="' + t.view_url_base + '/' + sub.id + '" class="btn btn-icon btn-text-secondary rounded-pill" title="' + esc(t.view) + '"><i class="ti ti-eye ti-md"></i></a>' +
      (t.can_manage
        ? '<a href="' + t.edit_url_base + '/' + sub.id + '" class="btn btn-icon btn-text-secondary rounded-pill" title="' + esc(t.edit) + '"><i class="ti ti-edit ti-md"></i></a>'
        : '') +
      '</div></li>'
    );
  }

  function render(mains) {
    // Searching sub-service names narrows each tab to the matches, so show them all;
    // searching main service names only picks tabs — their rows keep the normal fold.
    const searching = !!state.search && state.scope !== 'main';

    accordion.innerHTML = mains
      .map((m) => {
        const type = typeObj[m.service_type] || { title: m.service_type, icon: 'ti-category', class: 'bg-label-secondary' };
        const children = m.sub_services || [];
        // The true number of sub-services (of those matching, while searching) —
        // `children` is only the first page of them.
        const total = m.sub_services_count != null ? m.sub_services_count : children.length;
        const bodyId = 'subServiceMain-' + m.id;
        return (
          '<div class="card accordion-item active mb-4" data-main-id="' + m.id + '" data-total="' + total + '" data-loaded="' + children.length + '" data-shown="' + (searching ? children.length : Math.min(SUBS_SHOWN, children.length)) + '">' +
          '<h2 class="accordion-header d-flex align-items-center">' +
          '<button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#' + bodyId + '" aria-expanded="true">' +
          '<i class="ti ' + type.icon + ' me-2"></i>' +
          '<span class="me-2">' + esc(m.name) + '</span>' +
          '<span class="badge ' + type.class + ' me-2">' + esc(type.title) + '</span>' +
          (m.operational_status !== 'active' ? badge(statusObj, m.operational_status) : '') +
          '<span class="badge badge-center rounded-pill bg-label-secondary ms-2">' + total + '</span>' +
          '</button></h2>' +
          '<div id="' + bodyId + '" class="accordion-collapse collapse show">' +
          '<div class="accordion-body">' +
          '<div class="mb-2"><a href="' + t.main_view_url_base + '/' + m.id + '" class="small">' + esc(t.view) + ' <i class="ti ti-arrow-right ti-xs"></i></a></div>' +
          (children.length
            ? '<ul class="list-group" data-sub-list>' + children.map(subRow).join('') + '</ul>' +
              '<div class="d-flex flex-wrap align-items-center gap-2 mt-3" data-controls>' +
              '<small class="text-muted me-1" data-count></small>' +
              '<button type="button" class="btn btn-sm btn-primary js-subs-more"></button>' +
              '<button type="button" class="btn btn-sm btn-label-secondary js-subs-less"></button>' +
              (total > VIEW_ALL_FROM
                ? '<a href="' + t.main_view_url_base + '/' + m.id + '" class="btn btn-sm btn-text-primary ms-auto">' +
                  esc(fill(t.view_all, { count: total })) + ' <i class="ti ti-arrow-right ti-xs"></i></a>'
                : '') +
              '</div>'
            : '<p class="text-muted mb-0" data-empty-note>' + esc(t.no_subs_under_main) + '</p>') +
          '</div></div></div>'
        );
      })
      .join('');

    accordion.querySelectorAll('.accordion-item').forEach(refreshItem);
  }

  /**
   * One place decides what a tab shows: the first `shown` rows, the
   * "x of N" counter, and which of "Show more" / "Show less" apply.
   */
  function refreshItem(item) {
    const total = Number(item.dataset.total);
    const shown = Number(item.dataset.shown);
    const rows = item.querySelectorAll('[data-sub-row]');
    rows.forEach((li, i) => li.classList.toggle('d-none', i >= shown));

    const counter = item.querySelector('[data-count]');
    if (counter) counter.textContent = fill(t.of_total, { shown: Math.min(shown, total), total });

    const more = item.querySelector('.js-subs-more');
    const less = item.querySelector('.js-subs-less');
    if (more) {
      const remaining = total - shown;
      more.classList.toggle('d-none', remaining <= 0);
      more.innerHTML = '<i class="ti ti-plus ti-xs me-1"></i>' + esc(t.show_more) + ' (' + Math.min(SUBS_STEP, Math.max(remaining, 0)) + ')';
    }
    if (less) {
      less.classList.toggle('d-none', shown <= SUBS_SHOWN);
      less.innerHTML = '<i class="ti ti-minus ti-xs me-1"></i>' + esc(t.show_less);
    }
    // Nothing to fold or expand: no controls row at all.
    const controls = item.querySelector('[data-controls]');
    if (controls && total <= SUBS_SHOWN) controls.querySelectorAll('button, small').forEach((el) => el.classList.add('d-none'));
  }

  accordion.addEventListener('click', (e) => {
    const more = e.target.closest('.js-subs-more');
    const less = e.target.closest('.js-subs-less');
    if (!more && !less) return;
    const item = (more || less).closest('.accordion-item');

    if (less) {
      item.dataset.shown = String(SUBS_SHOWN);
      refreshItem(item);
      return;
    }

    const total = Number(item.dataset.total);
    const loaded = Number(item.dataset.loaded);
    const target = Math.min(Number(item.dataset.shown) + SUBS_STEP, total);

    // Everything needed is already on the page.
    if (target <= loaded) {
      item.dataset.shown = String(target);
      refreshItem(item);
      return;
    }

    // Otherwise fetch the next page of THIS main service's sub-services
    // (same search term, so a search keeps showing only what matches).
    more.disabled = true;
    more.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + esc(t.show_more);

    window.axios
      .get('/api/v1/sub-services', {
        params: {
          main_service_id: item.dataset.mainId,
          per_page: SUBS_STEP,
          page: Math.floor(loaded / SUBS_STEP) + 1,
          sort_by: 'name',
          // A main-service search doesn't narrow the sub-services of the matched tab.
          search: state.search && state.scope !== 'main' ? state.search : undefined,
          search_in: state.search && state.scope === 'sub' ? 'sub' : undefined
        }
      })
      .then((response) => {
        const fetched = response.data.data || [];
        item.querySelector('[data-sub-list]').insertAdjacentHTML('beforeend', fetched.map(subRow).join(''));
        item.dataset.loaded = String(loaded + fetched.length);
        item.dataset.shown = String(Math.min(target, loaded + fetched.length));
      })
      .catch((error) => {
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      })
      .finally(() => {
        more.disabled = false;
        refreshItem(item);
      });
  });

  // ------------------------------------------------------------ pagination
  function pageNumbers(current, last) {
    const pages = new Set([1, last, current - 1, current, current + 1]);
    const sorted = Array.from(pages).filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    const out = [];
    sorted.forEach((p, i) => {
      if (i && p - sorted[i - 1] > 1) out.push('…');
      out.push(p);
    });
    return out;
  }

  function renderFooter(meta) {
    const total = meta.total || 0;
    const from = total ? meta.from : 0;
    const to = total ? meta.to : 0;
    info.textContent = (total ? t.info : t.info_empty)
      .replace('_START_', from)
      .replace('_END_', to)
      .replace('_TOTAL_', total);

    const last = meta.last_page || 1;
    const current = meta.current_page || 1;
    const item = (label, page, opts) =>
      '<li class="page-item' + (opts.disabled ? ' disabled' : '') + (opts.active ? ' active' : '') + '">' +
      '<a class="page-link" href="#" data-page="' + page + '"' + (opts.aria ? ' aria-label="' + esc(opts.aria) + '"' : '') + '>' + label + '</a></li>';

    pager.innerHTML =
      item('<i class="ti ti-chevron-left ti-sm"></i>', current - 1, { disabled: current <= 1, aria: t.previous }) +
      pageNumbers(current, last)
        .map((p) => (p === '…' ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : item(p, p, { active: p === current })))
        .join('') +
      item('<i class="ti ti-chevron-right ti-sm"></i>', current + 1, { disabled: current >= last, aria: t.next });

    pager.classList.toggle('d-none', last <= 1);
    footer.classList.toggle('d-none', total === 0);
  }

  pager.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-page]');
    if (!link) return;
    e.preventDefault();
    if (link.parentElement.classList.contains('disabled') || link.parentElement.classList.contains('active')) return;
    state.page = Number(link.dataset.page);
    load(true);
  });

  // ---------------------------------------------------------------- loading
  let requestId = 0;

  function load(scrollToTop) {
    const mine = ++requestId; // a slower, older response must never overwrite a newer one
    loading.classList.remove('d-none');
    emptyBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    window.axios
      .get('/api/v1/main-services', {
        params: {
          per_page: state.perPage,
          page: state.page,
          sort_by: 'name',
          // Only the first page of each tab's sub-services — "Show more" fetches the rest.
          subs_limit: SUBS_STEP,
          subs_match: state.search && state.scope !== 'main' ? 1 : undefined,
          search: state.search || undefined,
          search_in: state.search && state.scope !== 'all' ? state.scope : undefined,
          service_type: state.type || undefined,
          operational_status: state.status || undefined
        }
      })
      .then((response) => {
        if (mine !== requestId) return;
        loading.classList.add('d-none');

        const meta = response.data.meta || {};
        // A page that no longer exists (e.g. the last row was filtered away) — go back to the last one.
        if (!response.data.data.length && meta.last_page && state.page > meta.last_page) {
          state.page = meta.last_page;
          return load(false);
        }

        render(response.data.data);
        renderFooter(meta);

        if (!meta.total) {
          emptyBox.textContent = state.search || state.type || state.status ? t.no_matches : t.no_main_services;
          emptyBox.classList.remove('d-none');
        }
        if (scrollToTop) window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch((error) => {
        if (mine !== requestId) return;
        loading.classList.add('d-none');
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
      });
  }

  // Any change to a filter starts again from page 1.
  const restart = () => {
    state.page = 1;
    load(false);
  };

  let searchTimer = null;
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.search = search.value.trim();
      restart();
    }, 500);
  });
  searchIn.addEventListener('change', () => {
    state.scope = searchIn.value;
    search.placeholder = scopes[state.scope].placeholder;
    // Nothing typed yet → nothing to re-run.
    if (state.search) restart();
  });
  typeFilter.addEventListener('change', () => {
    state.type = typeFilter.value;
    restart();
  });
  statusFilter.addEventListener('change', () => {
    state.status = statusFilter.value;
    restart();
  });
  perPageSelect.addEventListener('change', () => {
    state.perPage = Number(perPageSelect.value) || 10;
    restart();
  });

  load(false);
});
