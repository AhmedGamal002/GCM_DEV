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
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  function setDocLink(anchorId, doc, downloadType) {
    const anchor = document.getElementById(anchorId);
    if (doc && doc.has_attachment) {
      anchor.href = `/api/v1/drivers/${id}/documents/${downloadType}`;
      anchor.target = '_blank';
      anchor.classList.remove('d-none');
    }
  }

  function render(d) {
    document.getElementById('dv-name').textContent = d.name;
    document.getElementById('dv-code').textContent = d.code;
    document.getElementById('dv-email').textContent = d.email;
    document.getElementById('dv-phone').textContent = d.phone;
    document.getElementById('dv-affiliation').textContent = d.affiliation === 'gcm' ? 'GCM' : d.affiliation;

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

    document.getElementById('dv-residence-number').textContent = d.residence.number || '—';
    document.getElementById('dv-residence-valid-to').textContent = d.residence.valid_to || '—';
    setDocLink('dv-residence-download', d.residence, 'residence');

    document.getElementById('dv-license-number').textContent = d.license.number || '—';
    document.getElementById('dv-license-valid-to').textContent = d.license.valid_to || '—';
    setDocLink('dv-license-download', d.license, 'license');

    document.getElementById('dv-operational-license-number').textContent = d.operational_license.number || '—';
    document.getElementById('dv-operational-license-valid-to').textContent = d.operational_license.valid_to || '—';
    setDocLink('dv-operational-license-download', d.operational_license, 'operational-license');

    document.getElementById('dv-insurance-number').textContent = d.insurance.number || '—';
    document.getElementById('dv-insurance-valid-to').textContent = d.insurance.valid_to || '—';
    setDocLink('dv-insurance-download', d.insurance, 'insurance');

    const permitsBody = document.getElementById('dv-entry-permits-body');
    if (d.entry_permits && d.entry_permits.length) {
      permitsBody.innerHTML = d.entry_permits
        .map(function (p) {
          const attachment = p.has_attachment
            ? '<a href="/api/v1/driver-entry-permits/' + p.id + '/attachment" target="_blank">' + (t.download || 'Download') + '</a>'
            : t.no_attachment || 'No attachment';
          return (
            '<tr><td>' + escapeHtml(p.area_name) + '</td><td>' + escapeHtml(p.permit_number) + '</td><td>' + escapeHtml(p.valid_to) + '</td><td>' + attachment + '</td></tr>'
          );
        })
        .join('');
    } else {
      permitsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">—</td></tr>';
    }

    document.querySelector(`#driverStatusForm input[name="new_status"][value="${d.status}"]`).checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    document.getElementById('driver-documents-section').classList.remove('d-none');
    document.getElementById('driver-status-card').classList.remove('d-none');
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

    window.axios
      .get(`/api/v1/drivers/${id}`)
      .then((response) => window.axios.patch(`/api/v1/users/${response.data.data.user_id}/status`, { status }))
      .then(function () {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch(function (error) {
        errorBox.textContent =
          (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
