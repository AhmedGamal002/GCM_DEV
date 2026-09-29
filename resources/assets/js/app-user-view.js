'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.userViewTranslations || {};
  const id = window.userViewId;

  const errorBox = document.getElementById('user-view-error');
  const loading = document.getElementById('user-view-loading');
  const content = document.getElementById('user-view-content');

  const query = new URLSearchParams(window.location.search);
  if (query.get('saved')) {
    const box = document.getElementById('user-view-status');
    box.textContent = t.saved || 'Changes saved successfully.';
    box.classList.remove('d-none');
  } else if (query.get('status_updated')) {
    const box = document.getElementById('user-view-status');
    box.textContent = t.status_updated || 'Account status updated successfully.';
    box.classList.remove('d-none');
  }

  const statusObj = {
    active: { title: t.active, class: 'bg-label-success' },
    on_vacation: { title: t.on_vacation, class: 'bg-label-warning' },
    deactivated: { title: t.deactivated, class: 'bg-label-secondary' }
  };

  function showError(message) {
    loading.classList.add('d-none');
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
  }

  function render(u) {
    document.getElementById('uv-name').textContent = u.name;
    document.getElementById('uv-code').textContent = u.code;
    document.getElementById('uv-email').textContent = u.email;
    document.getElementById('uv-phone').textContent = u.phone;
    document.getElementById('uv-affiliation').textContent = u.affiliation === 'gcm' ? 'GCM' : u.affiliation;
    document.getElementById('uv-entity').textContent = u.entity_name || '';
    document.getElementById('uv-role').textContent = u.roles.join(', ');

    if (u.updated_by_name && t.last_updated_by) {
      const at = u.updated_at ? new Date(u.updated_at).toLocaleString() : '';
      document.getElementById('uv-updated-by').textContent = t.last_updated_by
        .replace(':name', u.updated_by_name)
        .replace(':at', at);
    }

    if (u.additional_data) {
      document.getElementById('uv-additional').innerHTML = u.additional_data;
      document.getElementById('uv-additional-wrapper').classList.remove('d-none');
    }

    const photo = document.getElementById('uv-photo');
    if (u.photo_url) {
      photo.src = u.photo_url;
    } else {
      const initials = (u.name.match(/\b\w/g) || []).slice(0, 2).join('').toUpperCase();
      const fallback = document.createElement('span');
      fallback.className = 'avatar-initial rounded-circle bg-label-primary mb-4 d-flex align-items-center justify-content-center';
      fallback.style.cssText = 'width: 120px; height: 120px; font-size: 2.5rem;';
      fallback.textContent = initials;
      photo.replaceWith(fallback);
    }

    const statusBadge = document.getElementById('uv-status');
    const s = statusObj[u.status] || { title: u.status, class: 'bg-label-secondary' };
    statusBadge.textContent = s.title;
    statusBadge.className = 'badge ' + s.class;

    document.getElementById('uv-edit-link').href = t.edit_url_base + '/' + u.id;

    document.querySelector(`#userStatusForm input[name="new_status"][value="${u.status}"]`).checked = true;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    document.getElementById('user-status-card').classList.remove('d-none');
  }

  window.axios
    .get(`/api/v1/users/${id}`)
    .then((response) => render(response.data.data))
    .catch(function (error) {
      showError(error.response && error.response.data && error.response.data.message);
    });

  // Account Status card — same confirm-checkbox-gates-the-button pattern
  // as the template's own "Delete Account" card.
  const statusForm = document.getElementById('userStatusForm');
  const confirmCheckbox = document.getElementById('us-confirm');
  const submitBtn = document.getElementById('us-submit');

  confirmCheckbox.addEventListener('change', function () {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const status = statusForm.querySelector('input[name="new_status"]:checked').value;

    window.gcmBusy.start();

    window.axios
      .patch(`/api/v1/users/${id}/status`, { status })
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
