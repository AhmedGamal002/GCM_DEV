'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('formPasswordChange');
  if (!form) return;

  const statusBox = document.getElementById('password-status');
  const errorBox = document.getElementById('password-error');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    statusBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    window.gcmBusy.start({ progress: true });

    window.axios
      .patch('/api/v1/me/password', {
        current_password: document.getElementById('currentPassword').value,
        new_password: document.getElementById('newPassword').value,
        new_password_confirmation: document.getElementById('confirmPassword').value
      }, { onUploadProgress: window.gcmBusy.onUploadProgress })
      .then(function () {
        window.gcmBusy.stop();
        statusBox.textContent = 'Password updated.';
        statusBox.classList.remove('d-none');
        form.reset();
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      });
  });
});
