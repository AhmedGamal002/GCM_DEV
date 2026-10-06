/**
 * General Settings page (system admin) — the document-expiry warning days for drivers and vehicles.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.settingsTranslations || {};

  const loading = document.getElementById('settings-loading');
  const form = document.getElementById('settingsForm');
  const okBox = document.getElementById('settings-ok');
  const errorBox = document.getElementById('settings-error');
  const fields = ['driver_expiry_warning_days', 'vehicle_expiry_warning_days'];

  function fill(data) {
    fields.forEach(function (name) {
      document.getElementById(name).value = data[name];
    });
  }

  function clearFeedback() {
    okBox.classList.add('d-none');
    errorBox.classList.add('d-none');
    form.querySelectorAll('[data-feedback]').forEach(function (el) {
      el.textContent = '';
      el.classList.add('d-none');
    });
  }

  window.axios
    .get('/api/v1/settings/document-expiry')
    .then(function (response) {
      fill(response.data.data);
      loading.classList.add('d-none');
      form.classList.remove('d-none');
    })
    .catch(function (error) {
      loading.classList.add('d-none');
      errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
      errorBox.classList.remove('d-none');
    });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearFeedback();

    const payload = {};
    fields.forEach(function (name) {
      payload[name] = document.getElementById(name).value;
    });

    window.gcmBusy.start();

    window.axios
      .patch('/api/v1/settings/document-expiry', payload)
      .then(function (response) {
        window.gcmBusy.stop();
        fill(response.data.data);
        okBox.textContent = t.saved;
        okBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      })
      .catch(function (error) {
        window.gcmBusy.stop();

        const errors = (error.response && error.response.status === 422 && error.response.data.errors) || {};
        fields.forEach(function (name) {
          if (!errors[name]) return;
          const feedback = document.getElementById(name).parentElement.querySelector('[data-feedback]');
          feedback.textContent = errors[name][0];
          feedback.classList.remove('d-none');
        });

        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
