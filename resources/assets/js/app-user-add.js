'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.userAddTranslations || {};
  const form = document.getElementById('userAddForm');
  if (!form) return;

  const statusBox = document.getElementById('user-add-status');
  const errorBox = document.getElementById('user-add-error');

  const editorEl = document.getElementById('additional-data-editor');
  const quill = new Quill(editorEl, {
    theme: 'snow',
    placeholder: ''
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    statusBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    // Only data_entry/auditor are creatable here — see StoreUserRequest's
    // docblock for why 'driver' is deliberately not an option on this form.
    const role = form.querySelector('input[name="role"]:checked').value;

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('email', document.getElementById('email').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('password', document.getElementById('password').value);
    data.append('password_confirmation', document.getElementById('password_confirmation').value);
    data.append('status', form.querySelector('input[name="status"]:checked').value);
    data.append('additional_data', quill.root.innerHTML);
    data.append('roles[0]', role);

    const photo = document.getElementById('photo').files[0];
    if (photo) {
      data.append('photo', photo);
    }

    window.axios
      .post('/api/v1/users', data, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(function () {
        window.location.href = '/app/user/list?created=1';
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
