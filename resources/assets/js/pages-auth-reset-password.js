/**
 * Reset-password form — submits to /api/v1/auth/reset-password via axios
 * instead of a classic form POST. See pages-auth-login.js /
 * LoginController's docblock for why.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#formAuthentication');

  if (!form) {
    return;
  }

  const errorBox = document.querySelector('#form-error');
  const submitButton = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    errorBox.classList.add('d-none');
    submitButton.disabled = true;

    window.axios
      .get('/sanctum/csrf-cookie')
      .then(function () {
        return window.axios.post('/api/v1/auth/reset-password', {
          token: form.querySelector('#token').value,
          email: form.querySelector('#email').value,
          password: form.querySelector('#password').value,
          password_confirmation: form.querySelector('#password_confirmation').value
        });
      })
      .then(function () {
        window.location.href = '/login';
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        submitButton.disabled = false;
      });
  });
});
