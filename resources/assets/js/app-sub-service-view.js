/**
 * Sub-service Details — FRD V01.14 §1.9.2: name / status, the compatible
 * container capacities, the recycling facilities, "requires a recycle
 * receipt?" and the (currently zero) statistics. The capacities and
 * facilities arrive with the sub-service itself and are naturally few, so
 * they are client-side DataTables (same as a vehicle's documents).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.subServiceViewTranslations || {};
  const id = window.subServiceViewId;

  const errorBox = document.getElementById('sub-service-view-error');
  const loading = document.getElementById('sub-service-view-loading');
  const content = document.getElementById('sub-service-view-content');

  const query = new URLSearchParams(window.location.search);
  const flash = query.get('saved') ? t.saved : query.get('created') ? t.created : null;
  if (flash) {
    const box = document.getElementById('sub-service-view-status');
    box.textContent = flash;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function esc(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  const label = (key) => (key && t[key]) || key || '—';
  const statusBadge = (status) => {
    const s = statusObj[status] || { title: status, class: 'bg-label-secondary' };
    return '<span class="badge ' + s.class + '">' + esc(s.title) + '</span>';
  };

  const dtOptions = (emptyMessage) => ({
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
    dom: '<"row"<"col-12"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
  });

  function render(s) {
    document.getElementById('ssv-name').textContent = s.name;

    const badge = document.getElementById('ssv-status');
    const st = statusObj[s.operational_status] || { title: s.operational_status, class: 'bg-label-secondary' };
    badge.textContent = st.title;
    badge.className = 'badge ' + st.class;

    if (s.main_service) {
      const holder = document.getElementById('ssv-main');
      const a = document.createElement('a');
      a.href = (t.main_view_url_base || '/app/main-service/view') + '/' + s.main_service.id;
      a.textContent = s.main_service.name;
      holder.appendChild(a);
      document.getElementById('ssv-type').textContent = label(s.main_service.service_type);
      document.getElementById('ssv-categories').innerHTML = (s.main_service.vehicle_categories || [])
        .map((c) => '<span class="badge bg-label-primary">' + esc(c.name) + '</span>')
        .join('');
    }

    document.getElementById('ssv-asset-type').textContent = label(s.asset_type);
    document.getElementById('ssv-receipt').textContent = s.requires_recycle_receipt ? t.yes : t.no;
    document.getElementById('ssv-color').innerHTML = s.skip_color_code
      ? '<span class="d-inline-block rounded-circle border me-1 align-middle" style="width:12px;height:12px;background:' +
        esc(s.skip_color_hex || 'transparent') + '"></span>' + esc(s.skip_color_label)
      : esc(t.none);

    if (s.additional_data) {
      document.getElementById('ssv-additional').innerHTML = s.additional_data;
      document.getElementById('ssv-additional-wrapper').classList.remove('d-none');
    }

    if (s.updated_by_name && t.last_updated_by) {
      const at = s.updated_at ? new Date(s.updated_at).toLocaleString() : '';
      document.getElementById('ssv-updated-by').textContent = t.last_updated_by.replace(':name', s.updated_by_name).replace(':at', at);
    }

    Object.entries(s.stats || {}).forEach(([key, val]) => {
      const el = document.querySelector(`[data-stat="${key}"]`);
      if (el) el.textContent = val;
    });

    document.getElementById('ssv-capacities-body').innerHTML = (s.capacities || [])
      .map(
        (c) =>
          '<tr><td>' + esc(c.name) + '</td><td>' + esc(label(c.applies_to)) + '</td><td>' + esc(c.capacity_cbm) + '</td><td>' +
          esc(c.capacity_ton) + '</td></tr>'
      )
      .join('');
    $('#ssv-capacities-table').DataTable(dtOptions(t.no_capacities));
    document.getElementById('ssv-capacities-card').classList.remove('d-none');

    const facilityBase = t.facility_view_url_base || '/app/facility/view';
    document.getElementById('ssv-facilities-body').innerHTML = (s.facilities || [])
      .map(
        (f) =>
          '<tr><td dir="ltr">' + esc(f.code) + '</td><td><a href="' + facilityBase + '/' + f.id + '">' + esc(f.name) + '</a></td><td>' +
          esc(label(f.environmental_service)) + '</td><td>' + statusBadge(f.operational_status) + '</td></tr>'
      )
      .join('');
    $('#ssv-facilities-table').DataTable(dtOptions(t.no_facilities));
    document.getElementById('ssv-facilities-card').classList.remove('d-none');

    const editLink = document.getElementById('ssv-edit-link');
    if (editLink) editLink.href = (t.edit_url_base || '/app/sub-service/edit') + '/' + s.id;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/sub-services/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => {
      loading.classList.add('d-none');
      errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
      errorBox.classList.remove('d-none');
    });
});
