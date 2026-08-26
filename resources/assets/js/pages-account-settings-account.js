'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('formAccountSettings');
  if (!form) return;

  const statusBox = document.getElementById('account-status');
  const errorBox = document.getElementById('account-error');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    statusBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    window.axios
      .patch('/api/v1/me', { name: document.getElementById('name').value })
      .then(function () {
        statusBox.textContent = 'Saved.';
        statusBox.classList.remove('d-none');
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      });
  });
});
