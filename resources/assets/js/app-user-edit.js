'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.userEditTranslations || {};
  const id = window.userEditId;

  const loading = document.getElementById('user-edit-loading');
  const driverRedirect = document.getElementById('user-edit-driver-redirect');
  const driverLink = document.getElementById('user-edit-driver-link');
  const clientRedirect = document.getElementById('user-edit-client-redirect');
  const clientLink = document.getElementById('user-edit-client-link');
  const contractorRedirect = document.getElementById('user-edit-contractor-redirect');
  const contractorLink = document.getElementById('user-edit-contractor-link');
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
    errorBox.textContent = message || (window.gcmI18n && window.gcmI18n.unknown);
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

      // Same for a client account — its company, project access and
      // signature/stamp live on the client account form, and this form would
      // re-role it to a GCM role.
      if (u.affiliation === 'client') {
        clientLink.href = t.client_edit_url_base + '/' + u.id;
        loading.classList.add('d-none');
        clientRedirect.classList.remove('d-none');
        return;
      }

      // And a contractor company's user — its contractor lives on the contractor user form.
      if (u.roles.includes('contractor_user')) {
        contractorLink.href = t.contractor_user_edit_url_base + '/' + u.id;
        loading.classList.add('d-none');
        contractorRedirect.classList.remove('d-none');
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
      if (role === 'system_admin') {
        // Fixed role — nothing to choose (and the admin account can't be deactivated).
        document.getElementById('job-role-wrapper').classList.add('d-none');
        form.querySelectorAll('input[name="role"]').forEach((r) => {
          r.checked = false;
          r.required = false;
        });
      } else {
        const roleInput = form.querySelector(`input[name="role"][value="${role}"]`);
        if (roleInput) {
          roleInput.checked = true;
        }
      }

      form.querySelector(`input[name="status"][value="${u.status}"]`).checked = true;
      if (window.userEditIsSelf) {
        form.querySelectorAll('input[name="status"]').forEach((r) => {
          r.required = false;
          r.closest('.col-md-6').classList.add('d-none');
        });
      }

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

    // The system admin's own account has no role to pick — the role block is hidden for it.
    const roleInput = form.querySelector('input[name="role"]:checked');
    const status = window.userEditIsSelf ? null : form.querySelector('input[name="status"]:checked').value;

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('email', document.getElementById('email').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('additional_data', quill.root.innerHTML);
    if (roleInput) data.append('roles[0]', roleInput.value);
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

    // Server limit: 2 MB per photo — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { default: 2048 });
    if (tooLarge) {
      showError(tooLarge);
      return;
    }

    window.gcmBusy.start({ progress: true });

    window.axios
      .post(`/api/v1/users/${id}`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      })
      .then(() => (status ? window.axios.patch(`/api/v1/users/${id}/status`, { status }) : null))
      .then(function () {
        window.location.href = `${t.view_url_base}/${id}?saved=1`;
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : error.response && error.response.data && error.response.data.message;

        showError(message);
      });
  });
});
