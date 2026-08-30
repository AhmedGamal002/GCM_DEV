'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.driverAddTranslations || {};
  const form = document.getElementById('driverAddForm');
  if (!form) return;

  const errorBox = document.getElementById('driver-add-error');
  const permitsList = document.getElementById('entry-permits-list');
  const permitTemplate = document.getElementById('entry-permit-row-template');
  const addPermitBtn = document.getElementById('add-entry-permit');

  const editorEl = document.getElementById('additional-data-editor');
  const quill = new Quill(editorEl, { theme: 'snow', placeholder: '' });

  addPermitBtn.addEventListener('click', function () {
    const row = permitTemplate.content.cloneNode(true);
    row.querySelector('.remove-entry-permit').addEventListener('click', function (e) {
      e.target.closest('.entry-permit-row').remove();
    });
    permitsList.appendChild(row);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    errorBox.classList.add('d-none');

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('email', document.getElementById('email').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('password', document.getElementById('password').value);
    data.append('password_confirmation', document.getElementById('password_confirmation').value);
    data.append('status', form.querySelector('input[name="status"]:checked').value);
    data.append('additional_data', quill.root.innerHTML);

    const photo = document.getElementById('photo').files[0];
    if (photo) data.append('photo', photo);

    ['residence', 'license', 'operational_license', 'insurance'].forEach(function (prefix) {
      data.append(`${prefix}_number`, document.getElementById(`${prefix}_number`).value);
      data.append(`${prefix}_valid_to`, document.getElementById(`${prefix}_valid_to`).value);
      const file = document.getElementById(`${prefix}_attachment`).files[0];
      if (file) data.append(`${prefix}_attachment`, file);
    });

    document.querySelectorAll('.entry-permit-row').forEach(function (row, i) {
      data.append(`entry_permits[${i}][area_name]`, row.querySelector('.entry-area-name').value);
      data.append(`entry_permits[${i}][permit_number]`, row.querySelector('.entry-permit-number').value);
      data.append(`entry_permits[${i}][valid_to]`, row.querySelector('.entry-valid-to').value);
      const file = row.querySelector('.entry-attachment').files[0];
      if (file) data.append(`entry_permits[${i}][attachment]`, file);
    });

    window.axios
      .post('/api/v1/drivers', data, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(function () {
        window.location.href = '/app/driver/list?created=1';
      })
      .catch(function (error) {
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : t.generic_error || 'Something went wrong. Please try again.';

        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
