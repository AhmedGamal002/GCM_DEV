'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.userEditTranslations || {};
  const id = window.userEditId;

  const loading = document.getElementById('user-edit-loading');
  const driverRedirect = document.getElementById('user-edit-driver-redirect');
  const driverLink = document.getElementById('user-edit-driver-link');
  const formWrapper = document.getElementById('user-edit-form-wrapper');
  const statusBox = document.getElementById('user-edit-status');
  const errorBox = document.getElementById('user-edit-error');
  const form = document.getElementById('userEditForm');
  const cancelLink = document.getElementById('user-edit-cancel');

  cancelLink.href = t.view_url_base + '/' + id;

  const quill = new Quill(document.getElementById('additional-data-editor'), {
    theme: 'snow',
    placeholder: ''
  });

  function showError(message) {
    errorBox.textContent = message || t.generic_error;
    errorBox.classList.remove('d-none');
    window.scrollTo(0, 0);
  }

  window.axios
    .get(`/api/v1/users/${id}`)
    .then(function (response) {
      const u = response.data.data;

      // A driver's editable record lives entirely under the Drivers
      // module — PATCH /api/v1/users/{id} is policy-blocked for them
      // server-side (see UserPolicy::update()), so show a redirect
      // instead of a form that could never actually save.
      if (u.driver_id) {
        driverLink.href = t.driver_edit_url_base + '/' + u.driver_id;
        loading.classList.add('d-none');
        driverRedirect.classList.remove('d-none');
        return;
      }

      document.getElementById('name').value = u.name;
      document.getElementById('email').value = u.email;
      document.getElementById('phone').value = u.phone;
      quill.root.innerHTML = u.additional_data || '';

      if (u.updated_by_name && t.last_updated_by) {
        const at = u.updated_at ? new Date(u.updated_at).toLocaleString() : '';
        document.getElementById('ue-updated-by').textContent = t.last_updated_by
          .replace(':name', u.updated_by_name)
          .replace(':at', at);
      }

      const role = u.roles[0];
      const roleInput = form.querySelector(`input[name="role"][value="${role}"]`);
      if (roleInput) {
        roleInput.checked = true;
      }

      form.querySelector(`input[name="status"][value="${u.status}"]`).checked = true;

      loading.classList.add('d-none');
      formWrapper.classList.remove('d-none');
    })
    .catch(function (error) {
      loading.classList.add('d-none');
      showError(error.response && error.response.data && error.response.data.message);
    });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    statusBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    const role = form.querySelector('input[name="role"]:checked').value;
    const status = form.querySelector('input[name="status"]:checked').value;

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('additional_data', quill.root.innerHTML);
    data.append('roles[0]', role);
    data.append('_method', 'PATCH');

    const newPassword = document.getElementById('password').value;
    if (newPassword) {
      data.append('password', newPassword);
      data.append('password_confirmation', document.getElementById('password_confirmation').value);
    }

    const photo = document.getElementById('photo').files[0];
    if (photo) {
      data.append('photo', photo);
    }

    window.axios
      .post(`/api/v1/users/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(() => window.axios.patch(`/api/v1/users/${id}/status`, { status }))
      .then(function () {
        window.location.href = `${t.view_url_base}/${id}?saved=1`;
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : error.response && error.response.data && error.response.data.message;

        showError(message);
      });
  });
});
