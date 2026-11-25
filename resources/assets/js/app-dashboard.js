/**
 * The dashboard (FRD V01.14 "لوحة التحكم", part 8.3) — draws what GET /api/v1/dashboard sends for this user: the
 * five trip statuses, the 30-day trips chart, the main services' share (donut), the resources by status, the
 * purchase orders by items status, and the latest ten notifications. A section the user may not see is absent from
 * the answer, so its block stays hidden. Every label and value is set as text.
 */
'use strict';

$(function () {
  const cfg = window.dashboardConfig;
  const t = cfg.t;
  const $id = (x) => document.getElementById(x);
  const colors = (window.config && window.config.colors) || {};
  const palette = [colors.primary, colors.success, colors.info, colors.warning, colors.danger, colors.secondary].filter(Boolean);

  const el = (tag, cls, text) => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;

    return node;
  };

  /** A stat card: an icon tile, the big number and a caption — the same look as the trips list's counters. */
  function statCard(holder, { label, value, color, icon, href, details }) {
    const col = el('div', 'col-xl col-md-4 col-sm-6');
    const card = el(href ? 'a' : 'div', 'card card-border-shadow-' + color + ' h-100 d-block text-body');
    if (href) card.href = href;
    const body = el('div', 'card-body');
    const top = el('div', 'd-flex align-items-center mb-2');
    const avatar = el('div', 'avatar me-4');
    const tile = el('span', 'avatar-initial rounded bg-label-' + color);
    tile.appendChild(el('i', 'ti ' + icon + ' ti-28px'));
    avatar.appendChild(tile);
    top.appendChild(avatar);
    top.appendChild(el('h4', 'mb-0', value));
    body.appendChild(top);
    body.appendChild(el('p', 'mb-1 text-heading fw-medium', label));
    (details || []).forEach((d) => {
      const line = el('div', 'small d-flex justify-content-between');
      line.appendChild(el('span', 'text-muted', d.label));
      line.appendChild(el('span', 'fw-medium', d.value));
      body.appendChild(line);
    });
    card.appendChild(body);
    col.appendChild(card);
    holder.appendChild(col);
  }

  const statusStyle = {
    awaiting_client_approval: ['warning', 'ti-hourglass'],
    client_approved: ['info', 'ti-circle-check'],
    client_issue: ['danger', 'ti-alert-triangle'],
    completed: ['success', 'ti-flag-check'],
    cancelled: ['secondary', 'ti-ban']
  };

  function renderStatuses(rows) {
    const holder = $id('db-status-cards');
    rows.forEach((row) => statCard(holder, { label: row.label, value: row.count, color: statusStyle[row.status][0], icon: statusStyle[row.status][1] }));
    $id('db-statuses').classList.remove('d-none');
  }

  function renderTripsChart(days) {
    $id('db-trips-card').classList.remove('d-none');
    new window.ApexCharts($id('db-trips-chart'), {
      chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
      series: [{ name: t.trips_chart, data: days.map((d) => d.count) }],
      xaxis: { categories: days.map((d) => moment(d.date).format('MM-DD')), tickAmount: 10, labels: { rotate: 0 } },
      yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
      dataLabels: { enabled: false },
      colors: [colors.primary],
      noData: { text: t.no_trips },
      grid: { strokeDashArray: 4 }
    }).render();
  }

  function renderServices(rows) {
    $id('db-services-card').classList.remove('d-none');
    const holder = $id('db-services-chart');
    if (!rows.length) {
      holder.appendChild(el('p', 'text-muted text-center my-6', t.no_trips));

      return;
    }
    new window.ApexCharts(holder, {
      chart: { type: 'donut', height: 300, fontFamily: 'inherit' },
      series: rows.map((r) => r.count),
      labels: rows.map((r) => r.name),
      colors: palette,
      legend: { position: 'bottom' },
      dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
      tooltip: { y: { formatter: (v) => v + ' ' + t.trips_word } },
      stroke: { width: 0 }
    }).render();
  }

  function renderResources(res) {
    const holder = $id('db-resource-cards');
    const lifecycle = (counts) => [
      { label: t.active, value: counts.active },
      { label: t.on_maintenance, value: counts.on_maintenance },
      { label: t.deactivated, value: counts.deactivated }
    ];
    const total = (counts) => Object.values(counts).reduce((a, b) => a + b, 0);
    const cards = [
      { label: cfg.t.drivers_label, counts: res.drivers, color: 'primary', icon: 'ti-steering-wheel', details: [{ label: t.active, value: res.drivers.active }, { label: t.on_vacation, value: res.drivers.on_vacation }, { label: t.deactivated, value: res.drivers.deactivated }] },
      { label: cfg.t.vehicles_label, counts: res.vehicles, color: 'info', icon: 'ti-truck', details: lifecycle(res.vehicles) },
      { label: cfg.t.containers_label, counts: res.containers, color: 'success', icon: 'ti-box', details: lifecycle(res.containers) },
      { label: cfg.t.tanks_label, counts: res.tanks, color: 'warning', icon: 'ti-droplet', details: lifecycle(res.tanks) }
    ];
    cards.forEach((c) => statCard(holder, { label: c.label, value: total(c.counts), color: c.color, icon: c.icon, details: c.details }));
    $id('db-resources').classList.remove('d-none');
  }

  function renderPurchaseOrders(po) {
    const holder = $id('db-po-cards');
    [['valid', 'success', 'ti-file-check'], ['near_exhaustion', 'warning', 'ti-hourglass-low'], ['exhausted', 'danger', 'ti-file-x']].forEach(([key, color, icon]) =>
      statCard(holder, { label: t[key], value: po[key], color, icon, href: cfg.urls.po_list })
    );
    $id('db-pos').classList.remove('d-none');
  }

  function renderNotifications(rows) {
    const body = $id('db-notifications');
    body.textContent = '';
    if (!rows.length) {
      const tr = el('tr');
      const td = el('td', 'text-center text-muted py-5', t.no_notifications);
      td.colSpan = 3;
      tr.appendChild(td);
      body.appendChild(tr);

      return;
    }
    rows.forEach((row) => {
      const tr = el('tr');
      const when = el('td');
      when.appendChild(el('span', '', row.created_at ? moment(row.created_at).format('YYYY-MM-DD hh:mm A') : ''));
      when.firstChild.dir = 'ltr';
      if (!row.read) when.appendChild(el('span', 'badge bg-label-primary ms-1', cfg.t.new_label));
      tr.appendChild(when);
      tr.appendChild(el('td', 'fw-medium', row.type_label));
      const detail = el('td');
      if (row.url) {
        const a = el('a', '', row.detail);
        a.href = row.url;
        detail.appendChild(a);
      } else {
        detail.textContent = row.detail;
      }
      tr.appendChild(detail);
      body.appendChild(tr);
    });
  }

  window.axios
    .get('/api/v1/dashboard')
    .then((response) => {
      const d = response.data.data;
      if (d.trip_statuses) renderStatuses(d.trip_statuses);
      if (d.trips_30_days) renderTripsChart(d.trips_30_days);
      if (d.services_share) renderServices(d.services_share);
      if (d.resources) renderResources(d.resources);
      if (d.purchase_orders) renderPurchaseOrders(d.purchase_orders);
      renderNotifications(d.notifications || []);
    })
    .catch((error) => {
      const alert = el('div', 'alert alert-danger', window.gcmErrorMessage(error));
      document.querySelector('.row.g-6').prepend(alert);
    });
});
