/**
 * Forgot-password form — submits to /api/v1/auth/forgot-password via
 * axios instead of a classic form POST. See pages-auth-login.js /
 * LoginController's docblock for why.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#formAuthentication');

  if (!form) {
    return;
  }

  const errorBox = document.querySelector('#form-error');
  const statusBox = document.querySelector('#form-status');
  const submitButton = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    errorBox.classList.add('d-none');
    statusBox.classList.add('d-none');
    submitButton.disabled = true;

    window.axios
      .get('/sanctum/csrf-cookie')
      .then(function () {
        return window.axios.post('/api/v1/auth/forgot-password', {
          email: form.querySelector('#email').value
        });
      })
      .then(function (response) {
        statusBox.textContent = response.data.message;
        statusBox.classList.remove('d-none');
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
      })
      .finally(function () {
        submitButton.disabled = false;
      });
  });
});
