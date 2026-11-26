/**
 * The reports page (FRD V01.14 "التقارير والاحصائيات", part 8.4). The nine filters come from /api/v1/reports/filters
 * (only what appears in the trips the user sees); every change redraws the figures and charts (/reports/charts —
 * not for the client's project auditor, who gets the Excel only) and the trips summary table (/reports/trips-summary),
 * and the Excel button downloads the same rows (/reports/trips-summary/export). One `params()` feeds all three.
 * Names are set as text.
 */
'use strict';

$(function () {
  const cfg = window.reportsConfig;
  const t = cfg.t;
  const $id = (x) => document.getElementById(x);
  const colors = (window.config && window.config.colors) || {};
  const escapeHtml = (value) => $('<div>').text(value == null ? '' : value).html();
  const charts = {};
  let lists = {};
  let table = null;

  const selects = () => document.querySelectorAll('#rp-filters select[data-key]');

  /** The filters as request params — the single source for the table, the charts and the Excel. */
  function params() {
    const out = {};
    selects().forEach((select) => {
      if (select.value) out[select.dataset.key] = select.value;
    });
    if (out.period === 'custom') {
      if ($id('rp-date-from').value) out.date_from = $id('rp-date-from').value;
      if ($id('rp-date-to').value) out.date_to = $id('rp-date-to').value;
    }

    return out;
  }

  function fill(select, rows, withAll) {
    select.textContent = '';
    if (withAll) select.appendChild(new Option(t.all, ''));
    rows.forEach((row) => select.appendChild(new Option(row.name == null ? '' : row.name, row.id)));
  }

  /** Projects follow the chosen client company. */
  function fillProjects() {
    const company = $id('rp-company').value;
    const current = $id('rp-project').value;
    fill($id('rp-project'), (lists.projects || []).filter((p) => !company || String(p.company_id) === company), true);
    if ([...$id('rp-project').options].some((o) => o.value === current)) $id('rp-project').value = current;
    window.refreshGcmSelect($id('rp-project'));
  }

  function refresh() {
    if (table) table.ajax.reload();
    if (cfg.seesCharts) loadCharts();
  }

  // ── charts ────────────────────────────────────────────────────────
  function draw(key, el, options) {
    if (charts[key]) charts[key].destroy();
    charts[key] = new window.ApexCharts(el, Object.assign({ chart: {} }, options));
    charts[key].render();
  }

  function loadCharts() {
    window.axios
      .get('/api/v1/reports/charts', { params: params() })
      .then((response) => {
        const d = response.data.data;
        document.querySelectorAll('[data-total]').forEach((el) => {
          const v = d.totals[el.dataset.total];
          el.textContent = Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 3 });
        });

        draw('timeline', $id('rp-timeline'), {
          chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
          series: [{ name: t.trips, data: d.timeline.points.map((p) => p.count) }],
          xaxis: { categories: d.timeline.points.map((p) => (d.timeline.unit === 'day' ? moment(p.key).format('MM-DD') : p.key)), tickAmount: 12, labels: { rotate: 0, hideOverlappingLabels: true } },
          yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
          plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
          dataLabels: { enabled: false },
          colors: [colors.primary],
          noData: { text: t.no_trips }
        });

        const withTrips = d.statuses.filter((s) => s.count > 0);
        draw('statuses', $id('rp-statuses'), {
          chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
          series: withTrips.map((s) => s.count),
          labels: withTrips.map((s) => s.label),
          colors: [colors.warning, colors.info, colors.danger, colors.success, colors.secondary].filter(Boolean),
          legend: { position: 'bottom' },
          noData: { text: t.no_trips }
        });

        draw('subServices', $id('rp-sub-services'), {
          chart: { type: 'bar', height: Math.max(220, d.sub_services.length * 38 + 80), toolbar: { show: false }, fontFamily: 'inherit' },
          series: [
            { name: t.trips, data: d.sub_services.map((s) => s.trips) },
            { name: t.tons, data: d.sub_services.map((s) => s.quantity_ton) }
          ],
          xaxis: { categories: d.sub_services.map((s) => s.name) },
          plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
          dataLabels: { enabled: false },
          colors: [colors.primary, colors.success],
          noData: { text: t.no_trips }
        });
      })
      .catch(() => {});
  }

  // ── the trips summary table ───────────────────────────────────────
  const statusClass = {
    awaiting_client_approval: 'bg-label-warning',
    client_approved: 'bg-label-info',
    client_issue: 'bg-label-danger',
    completed: 'bg-label-success',
    cancelled: 'bg-label-secondary'
  };

  table = $('#rp-table').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ordering: false,
    ajax: window.gcmServerSideAjax('/api/v1/reports/trips-summary', params, t.no_permission),
    columns: [
      { data: 'code' }, { data: 'company' }, { data: 'project' }, { data: 'date' }, { data: 'time' },
      { data: 'delivery_note' }, { data: 'waste_manifest' }, { data: 'sub_service' }, { data: 'capacity' }, { data: 'status' }
    ],
    columnDefs: [
      { targets: [0, 3, 4, 5, 6], render: (data) => '<span dir="ltr">' + escapeHtml(data == null || data === '' ? '—' : data) + '</span>' },
      { targets: [1, 2, 7, 8], render: (data) => escapeHtml(data == null || data === '' ? '—' : data) },
      { targets: 9, render: (data, type, full) => '<span class="badge ' + (statusClass[full.status] || 'bg-label-secondary') + '">' + escapeHtml(full.status_label) + '</span>' }
    ],
    language: {
      sLengthMenu: '_MENU_',
      emptyTable: t.no_trips,
      info: t.info,
      infoEmpty: t.info_empty,
      paginate: { next: '<i class="ti ti-chevron-right ti-sm"></i>', previous: '<i class="ti ti-chevron-left ti-sm"></i>' }
    },
    dom: '<"row mx-2 my-3"<"col-md-2"<"ms-n2"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
  });

  $id('rp-export').addEventListener('click', () => window.gcmExport('/api/v1/reports/trips-summary/export', 'xlsx', null, params));

  // ── the filters ───────────────────────────────────────────────────
  window.axios.get('/api/v1/reports/filters').then((response) => {
    lists = response.data.data;
    selects().forEach((select) => {
      if (select.dataset.key === 'period') {
        fill(select, lists.periods, true);
      } else if (select.dataset.key !== 'project_id') {
        fill(select, lists[select.dataset.list] || [], true);
      }
    });
    fillProjects();
    window.initGcmSelects($id('rp-filters'));

    $(selects()).on('change', function () {
      if (this.id === 'rp-period') {
        document.querySelectorAll('[data-custom]').forEach((el) => el.classList.toggle('d-none', this.value !== 'custom'));
      }
      if (this.id === 'rp-company') fillProjects();
      refresh();
    });
    [$id('rp-date-from'), $id('rp-date-to')].forEach((input) => input.addEventListener('change', refresh));

    if (cfg.seesCharts) loadCharts();
  });
});
