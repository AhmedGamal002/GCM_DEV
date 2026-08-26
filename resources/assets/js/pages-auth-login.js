/**
 * Login form — submits to /api/v1/auth/login via axios instead of a
 * classic form POST, so the web panel goes through the same endpoint the
 * driver mobile app will use later (see LoginController's docblock).
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#formAuthentication');

  if (!form) {
    return;
  }

  const errorBox = document.querySelector('#login-error');
  const submitButton = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    errorBox.classList.add('d-none');
    errorBox.textContent = '';
    submitButton.disabled = true;

    window.axios
      .get('/sanctum/csrf-cookie')
      .then(function () {
        return window.axios.post('/api/v1/auth/login', {
          email: form.querySelector('#email').value,
          password: form.querySelector('#password').value,
          remember: form.querySelector('#remember-me').checked
        });
      })
      .then(function () {
        window.location.href = '/dashboard';
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
