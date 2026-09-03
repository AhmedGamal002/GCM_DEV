/**
 * Asset Details — mirrors app-vehicle-view.js. Renders the asset, its
 * compatible vehicle categories, a stubbed Projects section, and the
 * "Asset Status" card (confirm-checkbox-gates-the-button).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.assetViewTranslations || {};
  const id = window.assetViewId;

  const errorBox = document.getElementById('asset-view-error');
  const loading = document.getElementById('asset-view-loading');
  const content = document.getElementById('asset-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved') || query.get('status_updated')) {
    const box = document.getElementById('asset-view-status');
    box.textContent = query.get('status_updated') ? t.status_updated : t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    on_maintenance: { title: t.on_maintenance, class: 'bg-label-warning' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };
  const typeLabels = { container: t.container || 'Container', tank: t.tank || 'Tank' };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  function esc(v) {
    const d = document.createElement('div');
    d.textContent = v == null ? '' : v;
    return d.innerHTML;
  }

  function render(a) {
    document.getElementById('av-name').textContent = a.name;
    document.getElementById('av-type').textContent = typeLabels[a.asset_type] || a.asset_type;
    document.getElementById('av-type-icon').className =
      (a.asset_type === 'tank' ? 'ti ti-droplet' : 'ti ti-box') + ' ti-36px';
    document.getElementById('av-capacity').textContent = a.capacity_category
      ? `${a.capacity_category.name} (${a.capacity_category.capacity_cbm} CBM / ${a.capacity_category.capacity_ton} TON)`
      : '—';
    document.getElementById('av-affiliation').textContent = a.affiliation === 'gcm' ? t.gcm || 'GCM' : a.affiliation;
    document.getElementById('av-entity').textContent = a.entity_name || '—';
    document.getElementById('av-purchase-date').textContent = a.purchase_date || '—';

    if (a.additional_data) {
      document.getElementById('av-additional').innerHTML = a.additional_data;
      document.getElementById('av-additional-wrapper').classList.remove('d-none');
    }

    if (a.updated_by_name && t.last_updated_by) {
      const at = a.updated_at ? new Date(a.updated_at).toLocaleString() : '';
      document.getElementById('av-updated-by').textContent = t.last_updated_by
        .replace(':name', a.updated_by_name)
        .replace(':at', at);
    }

    const badge = document.getElementById('av-status');
    const s = statusObj[a.operational_status] || { title: a.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    document.getElementById('av-edit-link').href = (t.edit_url_base || '/app/asset/edit') + '/' + a.id;

    const compat = a.compatible_vehicle_categories || [];
    if (compat.length) {
      document.getElementById('av-compat').innerHTML = compat
        .map((c) => '<span class="badge bg-label-primary">' + esc(c.name) + '</span>')
        .join('');
      document.getElementById('asset-compat-card').classList.remove('d-none');
    }

    const chosen = document.querySelector(`#assetStatusForm input[name="new_status"][value="${a.operational_status}"]`);
    if (chosen) chosen.checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    document.getElementById('asset-status-card').classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/assets/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  // Status card
  const statusForm = document.getElementById('assetStatusForm');
  const confirmCheckbox = document.getElementById('as-confirm');
  const submitBtn = document.getElementById('as-submit');

  confirmCheckbox.addEventListener('change', function () {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const checked = statusForm.querySelector('input[name="new_status"]:checked');
    if (!checked) return;

    window.axios
      .patch(`/api/v1/assets/${id}/status`, { status: checked.value })
      .then(() => {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch(function (error) {
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
