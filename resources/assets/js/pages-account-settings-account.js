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

    const data = new FormData();
    data.append('_method', 'PATCH');
    data.append('photo', photo);

    window.axios
      .post('/api/v1/me', data, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(function () {
        window.location.reload();
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : form.dataset.genericError;

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      });
  });
});
