/**
 * Vehicle Details — mirrors app-user-view.js. Renders the vehicle, its
 * documents (with Gate-checked download links), a stubbed Trips section,
 * and the "Vehicle Status" card (confirm-checkbox-gates-the-button, same
 * pattern as the users page).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.vehicleViewTranslations || {};
  const id = window.vehicleViewId;

  const errorBox = document.getElementById('vehicle-view-error');
  const loading = document.getElementById('vehicle-view-loading');
  const content = document.getElementById('vehicle-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved') || query.get('status_updated')) {
    const box = document.getElementById('vehicle-view-status');
    box.textContent = query.get('status_updated') ? t.status_updated : t.saved;
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    on_maintenance: { title: t.on_maintenance, class: 'bg-label-warning' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

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

  function renderPhoto(containerId, url) {
    const el = document.getElementById(containerId);
    // Fixed-height framed box, image shown whole (object-fit: contain) so
    // nothing is cropped regardless of the photo's aspect ratio — front
    // and back are rendered identically.
    if (url) {
      el.innerHTML =
        '<a href="' + url + '" target="_blank" rel="noopener" ' +
        'class="d-flex align-items-center justify-content-center rounded border bg-body-secondary" ' +
        'style="height:220px;overflow:hidden">' +
        '<img src="' + url + '" alt="" style="max-width:100%;max-height:100%;object-fit:contain">' +
        '</a>';
    } else {
      el.innerHTML =
        '<div class="rounded border d-flex align-items-center justify-content-center text-muted bg-body-secondary" ' +
        'style="height:220px;font-size:2rem">—</div>';
    }
  }

  function render(v) {
    document.getElementById('vv-plate').textContent = v.plate;
    document.getElementById('vv-category').textContent = (v.category && v.category.name) || '';
    document.getElementById('vv-embedded').textContent = v.has_embedded_container
      ? `${v.embedded_container_type}${v.embedded_capacity_category ? ' — ' + v.embedded_capacity_category.name : ''}`
      : '—';
    document.getElementById('vv-affiliation').textContent = v.affiliation === 'gcm' ? t.gcm || 'GCM' : v.affiliation;
    document.getElementById('vv-entity').textContent = v.entity_name || '';

    if (v.updated_by_name && t.last_updated_by) {
      const at = v.updated_at ? new Date(v.updated_at).toLocaleString() : '';
      document.getElementById('vv-updated-by').textContent = t.last_updated_by
        .replace(':name', v.updated_by_name)
        .replace(':at', at);
    }

    if (v.additional_data) {
      document.getElementById('vv-additional').innerHTML = v.additional_data;
      document.getElementById('vv-additional-wrapper').classList.remove('d-none');
    }

    const photo = document.getElementById('vv-photo');
    if (v.photo_front_url) {
      photo.src = v.photo_front_url;
    } else {
      photo.replaceWith(Object.assign(document.createElement('div'), {
        className: 'bg-label-secondary rounded mb-4 d-flex align-items-center justify-content-center',
        textContent: '—',
        style: 'width:100%;height:260px;font-size:3rem'
      }));
    }

    // Vehicle Photos section — front and back, click to open full size.
    renderPhoto('vv-photo-front', v.photo_front_url);
    renderPhoto('vv-photo-back', v.photo_back_url);
    document.getElementById('vehicle-photos-card').classList.remove('d-none');

    const badge = document.getElementById('vv-status');
    const s = statusObj[v.operational_status] || { title: v.operational_status, class: 'bg-label-secondary' };
    badge.textContent = s.title;
    badge.className = 'badge ' + s.class;

    document.getElementById('vv-edit-link').href = (t.edit_url_base || '/app/vehicle/edit') + '/' + v.id;

    // Documents and truck entry permits — two separate sections.
    const attachmentCell = (d) =>
      d.has_attachment
        ? '<a href="' + d.download_url + '" class="btn btn-sm btn-text-primary"><i class="ti ti-download"></i></a>'
        : '—';

    const allDocs = v.documents || [];
    const documents = allDocs.filter((d) => d.type !== 'entry_permit');
    const permits = allDocs.filter((d) => d.type === 'entry_permit');

    if (documents.length) {
      document.getElementById('vv-docs-body').innerHTML = documents
        .map(
          (d) =>
            '<tr>' +
            '<td>' + esc((t.doc_labels && t.doc_labels[d.type]) || d.type) + '</td>' +
            '<td>' + esc(d.document_number) + '</td>' +
            '<td>' + esc(d.valid_to) + '</td>' +
            '<td>' + attachmentCell(d) + '</td></tr>'
        )
        .join('');
      document.getElementById('vehicle-docs-card').classList.remove('d-none');
    }

    document.getElementById('vehicle-permits-card').classList.remove('d-none');
    if (permits.length) {
      document.getElementById('vv-permits-body').innerHTML = permits
        .map(
          (d) =>
            '<tr>' +
            '<td>' + esc(d.area_name) + '</td>' +
            '<td>' + esc(d.document_number) + '</td>' +
            '<td>' + esc(d.valid_to) + '</td>' +
            '<td>' + attachmentCell(d) + '</td></tr>'
        )
        .join('');
      document.getElementById('vv-permits-table').classList.remove('d-none');
    } else {
      document.getElementById('vv-permits-empty').classList.remove('d-none');
    }

    document.querySelector(`#vehicleStatusForm input[name="new_status"][value="${v.operational_status}"]`).checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    document.getElementById('vehicle-status-card').classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/vehicles/${id}`)
    .then((response) => render(response.data.data))
    .catch((error) => showError(error.response && error.response.data && error.response.data.message));

  // Status card
  const statusForm = document.getElementById('vehicleStatusForm');
  const confirmCheckbox = document.getElementById('vs-confirm');
  const submitBtn = document.getElementById('vs-submit');

  confirmCheckbox.addEventListener('change', function () {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const status = statusForm.querySelector('input[name="new_status"]:checked').value;

    window.axios
      .patch(`/api/v1/vehicles/${id}/status`, { status })
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
