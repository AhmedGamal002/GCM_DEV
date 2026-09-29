'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('formAccountSettings');
  if (!form) return;

  const statusBox = document.getElementById('account-status');
  const errorBox = document.getElementById('account-error');
  const photoInput = document.getElementById('photo');
  const photoPreview = document.getElementById('account-photo-preview');

  photoInput.addEventListener('change', function () {
    const file = photoInput.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
      photoPreview.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    statusBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    const photo = photoInput.files[0];
    if (!photo) {
      errorBox.textContent = form.dataset.noPhotoMessage;
      errorBox.classList.remove('d-none');
      return;
    }

    // Server limit: 2 MB — fail now, not after the upload.
    const tooLarge = window.gcmFileGuard(form, { default: 2048 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      return;
    }

    const data = new FormData();
    data.append('_method', 'PATCH');
    data.append('photo', photo);

    window.gcmBusy.start({ progress: true });

    window.axios
      .post('/api/v1/me', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      })
      .then(function () {
        window.location.reload();
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : form.dataset.genericError;

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      });
  });
});

// Change Password — second card on the same profile page (was its own
// tab/page before; merged in per the client's request). Only rendered for
// roles User::canChangeOwnPassword() allows, so the form may not exist.
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
        statusBox.textContent = form.dataset.updatedMessage || 'Password updated.';
        statusBox.classList.remove('d-none');
        form.reset();
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : form.dataset.genericError || 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      });
  });
});
