'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.driverViewTranslations || {};
  const id = window.driverViewId;

  const errorBox = document.getElementById('driver-view-error');
  const loading = document.getElementById('driver-view-loading');
  const content = document.getElementById('driver-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('driver-view-status');
    box.textContent = t.saved || 'Changes saved successfully.';
    box.classList.remove('d-none');
  } else if (query.get('status_updated')) {
    const box = document.getElementById('driver-view-status');
    box.textContent = t.status_updated || 'Account status updated successfully.';
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    on_vacation: { title: t.on_vacation, class: 'bg-label-warning' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : value;
    return div.innerHTML;
  }

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || (window.gcmI18n && window.gcmI18n.unknown);
    errorBox.classList.remove('d-none');
  }

  function render(d) {
    if (d.updated_by_name && t.last_updated_by) {
      const at = d.updated_at ? new Date(d.updated_at).toLocaleString() : '';
      document.getElementById('dv-updated-by').textContent = t.last_updated_by
        .replace(':name', d.updated_by_name)
        .replace(':at', at);
    }

    document.getElementById('dv-name').textContent = d.name;
    document.getElementById('dv-code').textContent = d.code;
    document.getElementById('dv-email').textContent = d.email;
    document.getElementById('dv-phone').textContent = d.phone;
    document.getElementById('dv-affiliation').textContent = d.affiliation === 'gcm' ? 'GCM' : d.entity_name || d.affiliation;
    document.getElementById('dv-default-vehicle').textContent = d.default_vehicle
      ? d.default_vehicle.plate + (d.default_vehicle.category ? ' — ' + d.default_vehicle.category : '')
      : (t.not_set || '—');

    if (d.additional_data) {
      document.getElementById('dv-additional').innerHTML = d.additional_data;
      document.getElementById('dv-additional-wrapper').classList.remove('d-none');
    }

    const photo = document.getElementById('dv-photo');
    if (d.photo_url) {
      photo.src = d.photo_url;
    } else {
      const initials = (d.name.match(/\b\w/g) || []).slice(0, 2).join('').toUpperCase();
      const fallback = document.createElement('span');
      fallback.className = 'avatar-initial rounded-circle bg-label-primary mb-4 d-flex align-items-center justify-content-center';
      fallback.style.cssText = 'width: 120px; height: 120px; font-size: 2.5rem;';
      fallback.textContent = initials;
      photo.replaceWith(fallback);
    }

    const statusBadge = document.getElementById('dv-status');
    const s = statusObj[d.status] || { title: d.status, class: 'bg-label-secondary' };
    statusBadge.textContent = s.title;
    statusBadge.className = 'badge ' + s.class;

    document.getElementById('dv-edit-link').href = t.edit_url_base + '/' + d.id;

    // One table for the four dated documents, like the vehicle page: number, end date, the bar with the days
    // left, the Valid / Need review / Expired tag and the attachment.
    const attachmentCell = function (url) {
      return url ? '<a href="' + url + '" target="_blank" class="btn btn-sm btn-text-primary"><i class="ti ti-download"></i></a>' : '—';
    };
    const docRows = [
      ['residence', d.residence, 'residence'],
      ['license', d.license, 'license'],
      ['operational_license', d.operational_license, 'operational-license'],
      ['insurance', d.insurance, 'insurance']
    ];
    document.getElementById('dv-docs-body').innerHTML = docRows
      .map(function (row) {
        const doc = row[1];
        return (
          '<tr>' +
          '<td>' + escapeHtml((t.doc_labels && t.doc_labels[row[0]]) || row[0]) + '</td>' +
          '<td>' + escapeHtml(doc.number || '—') + '</td>' +
          '<td>' + escapeHtml(doc.valid_to || '—') + '</td>' +
          '<td>' + window.gcmExpiryBar(doc.expiry) + '</td>' +
          '<td>' + window.gcmExpiryTag(doc.expiry) + '</td>' +
          '<td>' + attachmentCell(doc.has_attachment ? '/api/v1/drivers/' + id + '/documents/' + row[2] : null) + '</td>' +
          '</tr>'
        );
      })
      .join('');
    $('#dv-docs-table').DataTable(window.gcmDocTableOptions(t, t.no_documents || 'No documents recorded for this driver.'));

    const permitsBody = document.getElementById('dv-entry-permits-body');
    permitsBody.innerHTML = (d.entry_permits || [])
      .map(function (p) {
        return (
          '<tr>' +
          '<td>' + escapeHtml(p.area_name) + '</td>' +
          '<td>' + escapeHtml(p.permit_number) + '</td>' +
          '<td>' + escapeHtml(p.valid_to) + '</td>' +
          '<td>' + window.gcmExpiryBar(p.expiry) + '</td>' +
          '<td>' + window.gcmExpiryTag(p.expiry) + '</td>' +
          '<td>' + attachmentCell(p.has_attachment ? '/api/v1/driver-entry-permits/' + p.id + '/attachment' : null) + '</td>' +
          '</tr>'
        );
      })
      .join('');

    // Same DataTables look as the list pages (search + pagination), on data that's already loaded — no
    // server round-trip for a handful of rows. Initialised once the rows are in the DOM.
    $('#dv-entry-permits-table').DataTable(window.gcmDocTableOptions(t, t.no_entry_permits || 'No entry permits recorded for this driver.'));
    document.getElementById('driver-permits-card').classList.remove('d-none');

    document.querySelector(`#driverStatusForm input[name="new_status"][value="${d.status}"]`).checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    document.getElementById('driver-documents-section').classList.remove('d-none');
    window.gcmRecalcDocTables();
    if (window.gcmCanManage) document.getElementById('driver-status-card').classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/drivers/${id}`)
    .then((response) => render(response.data.data))
    .catch(function (error) {
      showError(error.response && error.response.data && error.response.data.message);
    });

  const statusForm = document.getElementById('driverStatusForm');
  const confirmCheckbox = document.getElementById('ds-confirm');
  const submitBtn = document.getElementById('ds-submit');

  confirmCheckbox.addEventListener('change', function () {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const status = statusForm.querySelector('input[name="new_status"]:checked').value;

    window.gcmBusy.start();

    window.axios
      .get(`/api/v1/drivers/${id}`)
      .then((response) => window.axios.patch(`/api/v1/users/${response.data.data.user_id}/status`, { status }))
      .then(function () {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        errorBox.textContent =
          (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
