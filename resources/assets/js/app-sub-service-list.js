/**
 * Sub Services page — FRD V01.14 §1.9.2: an "Accordion With Icon" (open by
 * default, collapsible) with one tab per main service and its sub-services
 * inside, each with a view and an edit icon. Every main service and
 * sub-service is loaded (gcmFetchAll — no fixed per_page), so the search
 * box filters client-side across the name of both.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.subServiceListTranslations || {};
  const accordion = document.getElementById('subServiceAccordion');
  const loading = document.getElementById('sub-service-list-loading');
  const emptyBox = document.getElementById('sub-service-list-empty');
  const errorBox = document.getElementById('sub-service-list-error');
  const search = document.getElementById('sub-service-search');

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

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  const badge = (obj, key) => {
    const s = obj[key] || { title: key, class: 'bg-label-secondary' };
    return '<span class="badge ' + s.class + '">' + esc(s.title) + '</span>';
  };

  // A main service can group many sub-services — each tab shows the first
  // few and folds the rest behind a "+" button (same as the main-services table).
  const SUBS_SHOWN = 3;

  function subRow(sub) {
    const swatch = sub.skip_color_code
      ? '<span class="d-inline-block rounded-circle border me-1" style="width:12px;height:12px;background:' +
        esc(sub.skip_color_hex || 'transparent') + '" title="' + esc(sub.skip_color_label) + '"></span>'
      : '';
    return (
      '<li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2" data-sub-name="' + esc(sub.name.toLowerCase()) + '">' +
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

  function render(mains, subs) {
    const byMain = {};
    subs.forEach((s) => (byMain[s.main_service_id] = byMain[s.main_service_id] || []).push(s));

    if (!mains.length) {
      emptyBox.textContent = t.no_main_services;
      emptyBox.classList.remove('d-none');
      return;
    }

    accordion.innerHTML = mains
      .map((m) => {
        const type = typeObj[m.service_type] || { title: m.service_type, icon: 'ti-category', class: 'bg-label-secondary' };
        const children = byMain[m.id] || [];
        const bodyId = 'subServiceMain-' + m.id;
        return (
          '<div class="card accordion-item active mb-4" data-main-name="' + esc(m.name.toLowerCase()) + '">' +
          '<h2 class="accordion-header d-flex align-items-center">' +
          '<button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#' + bodyId + '" aria-expanded="true">' +
          '<i class="ti ' + type.icon + ' me-2"></i>' +
          '<span class="me-2">' + esc(m.name) + '</span>' +
          '<span class="badge ' + type.class + ' me-2">' + esc(type.title) + '</span>' +
          (m.operational_status !== 'active' ? badge(statusObj, m.operational_status) : '') +
          '<span class="badge badge-center rounded-pill bg-label-secondary ms-2">' + children.length + '</span>' +
          '</button></h2>' +
          '<div id="' + bodyId + '" class="accordion-collapse collapse show">' +
          '<div class="accordion-body">' +
          '<div class="mb-2"><a href="' + t.main_view_url_base + '/' + m.id + '" class="small">' + esc(t.view) + ' <i class="ti ti-arrow-right ti-xs"></i></a></div>' +
          (children.length
            ? '<ul class="list-group">' + children.map(subRow).join('') + '</ul>' +
              // A labelled "Show more" / "Show less" button (primary, like
              // "Create New"), aligned to the start of the list; the hidden
              // count is in the tooltip.
              (children.length > SUBS_SHOWN
                ? '<div class="mt-3"><button type="button" class="btn btn-sm btn-primary js-subs-toggle" data-hidden-count="' +
                  (children.length - SUBS_SHOWN) + '"></button></div>'
                : '')
            : '<p class="text-muted mb-0" data-empty-note>' + esc(t.no_subs_under_main) + '</p>') +
          '</div></div></div>'
        );
      })
      .join('');

    accordion.querySelectorAll('.accordion-item').forEach((item) => refreshItem(item, ''));
  }

  /**
   * One place decides which sub-service rows of a main-service tab are shown:
   *  - while searching: every match (the fold is ignored, so a match beyond
   *    the first few is never hidden) and no toggle button;
   *  - otherwise: the first SUBS_SHOWN rows, or all of them once expanded.
   * Returns how many rows are shown.
   */
  function refreshItem(item, term) {
    const mainMatch = !term || item.dataset.mainName.includes(term);
    const expanded = item.dataset.expanded === '1';
    let shown = 0;

    item.querySelectorAll('[data-sub-name]').forEach((li, i) => {
      const show = term ? mainMatch || li.dataset.subName.includes(term) : expanded || i < SUBS_SHOWN;
      li.classList.toggle('d-none', !show);
      if (show) shown++;
    });

    const btn = item.querySelector('.js-subs-toggle');
    if (btn) {
      btn.parentElement.classList.toggle('d-none', !!term);
      btn.innerHTML =
        '<i class="ti ' + (expanded ? 'ti-minus' : 'ti-plus') + ' ti-xs me-1"></i>' + esc(expanded ? t.show_less : t.show_more);
      btn.title = expanded ? t.show_less : t.show_more + ' (' + btn.dataset.hiddenCount + ')';
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    return { mainMatch, shown };
  }

  accordion.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-subs-toggle');
    if (!btn) return;
    const item = btn.closest('.accordion-item');
    item.dataset.expanded = item.dataset.expanded === '1' ? '0' : '1';
    refreshItem(item, '');
  });

  // Client-side search across main and sub-service names.
  function applySearch() {
    const term = search.value.trim().toLowerCase();
    let anyVisible = false;

    accordion.querySelectorAll('.accordion-item').forEach((item) => {
      const { mainMatch, shown } = refreshItem(item, term);
      const visible = mainMatch || shown > 0;
      item.classList.toggle('d-none', !visible);
      if (visible) anyVisible = true;
    });

    if (term && !anyVisible) {
      emptyBox.textContent = t.no_matches;
      emptyBox.classList.remove('d-none');
    } else if (accordion.children.length) {
      emptyBox.classList.add('d-none');
    }
  }

  search.addEventListener('input', applySearch);

  Promise.all([
    window.gcmFetchAll('/api/v1/main-services', { sort_by: 'name' }),
    window.gcmFetchAll('/api/v1/sub-services', { sort_by: 'name' })
  ])
    .then(([mains, subs]) => {
      loading.classList.add('d-none');
      render(mains, subs);
    })
    .catch((error) => {
      loading.classList.add('d-none');
      errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
      errorBox.classList.remove('d-none');
    });
});
