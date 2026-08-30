'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.driverEditTranslations || {};
  const id = window.driverEditId;

  const loading = document.getElementById('driver-edit-loading');
  const formWrapper = document.getElementById('driver-edit-form-wrapper');
  const errorBox = document.getElementById('driver-edit-error');
  const form = document.getElementById('driverEditForm');
  const cancelLink = document.getElementById('driver-edit-cancel');

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
    .get(`/api/v1/drivers/${id}`)
    .then(function (response) {
      const d = response.data.data;

      document.getElementById('name').value = d.name;
      document.getElementById('email').value = d.email;
      document.getElementById('phone').value = d.phone;
      quill.root.innerHTML = d.additional_data || '';

      document.getElementById('residence_number').value = d.residence.number || '';
      document.getElementById('residence_valid_to').value = d.residence.valid_to || '';

      document.getElementById('license_number').value = d.license.number || '';
      document.getElementById('license_valid_to').value = d.license.valid_to || '';

      document.getElementById('operational_license_number').value = d.operational_license.number || '';
      document.getElementById('operational_license_valid_to').value = d.operational_license.valid_to || '';

      document.getElementById('insurance_number').value = d.insurance.number || '';
      document.getElementById('insurance_valid_to').value = d.insurance.valid_to || '';

      loading.classList.add('d-none');
      formWrapper.classList.remove('d-none');
    })
    .catch(function (error) {
      loading.classList.add('d-none');
      showError(error.response && error.response.data && error.response.data.message);
    });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    errorBox.classList.add('d-none');

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('additional_data', quill.root.innerHTML);
    data.append('_method', 'PATCH');

    ['residence', 'license', 'operational_license', 'insurance'].forEach(function (prefix) {
      data.append(`${prefix}_number`, document.getElementById(`${prefix}_number`).value);
      data.append(`${prefix}_valid_to`, document.getElementById(`${prefix}_valid_to`).value);
      const file = document.getElementById(`${prefix}_attachment`).files[0];
      if (file) data.append(`${prefix}_attachment`, file);
    });

    const photo = document.getElementById('photo').files[0];
    if (photo) data.append('photo', photo);

    window.axios
      .post(`/api/v1/drivers/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } })
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
