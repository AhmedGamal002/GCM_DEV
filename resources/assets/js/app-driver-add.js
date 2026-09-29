'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.driverAddTranslations || {};
  const form = document.getElementById('driverAddForm');
  if (!form) return;

  const errorBox = document.getElementById('driver-add-error');
  const permitsList = document.getElementById('entry-permits-list');
  const permitTemplate = document.getElementById('entry-permit-row-template');
  const addPermitBtn = document.getElementById('add-entry-permit');
  const categoryCheckboxes = document.getElementById('vehicle-category-checkboxes');
  const defaultVehicleSelect = document.getElementById('default_vehicle_id');
  window.initGcmSelects(form);

  const editorEl = document.getElementById('additional-data-editor');
  const quill = new Quill(editorEl, { theme: 'snow', placeholder: '' });

  // FRD's "Default Vehicle" section: the checked category(ies) determine
  // which vehicles are offered as the default — a vehicle only shows up
  // once qualification for its own category is checked.
  function refreshVehicleOptions() {
    const checked = Array.from(categoryCheckboxes.querySelectorAll('input:checked'));

    if (checked.length === 0) {
      defaultVehicleSelect.innerHTML = '<option value="">' + (t.select_types_first || 'Select vehicle type(s) first') + '</option>';
      defaultVehicleSelect.disabled = true;
      window.refreshGcmSelect(defaultVehicleSelect);
      return;
    }

    defaultVehicleSelect.disabled = true;
    defaultVehicleSelect.innerHTML = '<option value="">' + (t.loading || 'Loading...') + '</option>';
    window.refreshGcmSelect(defaultVehicleSelect);

    Promise.all(
      checked.map((cb) => window.axios.get('/api/v1/vehicles', {
        // unassigned_as_default: hide any vehicle already set as another
        // driver's default — this is a new driver, so there's no "own"
        // vehicle to keep visible (unlike the edit page).
        params: { category: cb.value, operational_status: 'active', per_page: 200, unassigned_as_default: 1 }
      }))
    )
      .then(function (responses) {
        const byId = new Map();
        responses.forEach((res) => res.data.data.forEach((v) => byId.set(v.id, v)));

        const previousValue = defaultVehicleSelect.value;
        defaultVehicleSelect.innerHTML = '<option value="">' + (t.select_vehicle || 'Select a vehicle') + '</option>';
        byId.forEach(function (v) {
          const opt = document.createElement('option');
          opt.value = v.id;
          opt.textContent = v.plate + (v.category && v.category.name ? ' — ' + v.category.name : '');
          defaultVehicleSelect.appendChild(opt);
        });
        if (byId.has(Number(previousValue))) {
          defaultVehicleSelect.value = previousValue;
        }
        defaultVehicleSelect.disabled = false;
        window.refreshGcmSelect(defaultVehicleSelect);
      })
      .catch(function () {
        defaultVehicleSelect.innerHTML = '<option value="">' + (t.generic_error || 'Something went wrong. Please try again.') + '</option>';
        window.refreshGcmSelect(defaultVehicleSelect);
      });
  }

  window.axios
    .get('/api/v1/vehicle-categories')
    .then(function (response) {
      response.data.data.forEach(function (category) {
        const wrapper = document.createElement('div');
        wrapper.className = 'form-check';
        wrapper.innerHTML =
          '<input class="form-check-input vehicle-category-checkbox" type="checkbox" value="' + category.slug + '" data-category-id="' + category.id + '" id="vc-' + category.slug + '">' +
          '<label class="form-check-label" for="vc-' + category.slug + '">' + $('<div>').text(category.name).html() + '</label>';
        categoryCheckboxes.appendChild(wrapper);
      });
      categoryCheckboxes.querySelectorAll('input').forEach((cb) => cb.addEventListener('change', refreshVehicleOptions));
    })
    .catch(function () {
      categoryCheckboxes.textContent = t.generic_error || 'Something went wrong. Please try again.';
    });

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

    // Server limits: photo 2 MB, every document 5 MB — checked here so a
    // too-big file fails now, not after the whole upload.
    const tooLarge = window.gcmFileGuard(form, { default: 5120, photo: 2048 });
    if (tooLarge) {
      errorBox.textContent = tooLarge;
      errorBox.classList.remove('d-none');
      window.scrollTo(0, 0);
      return;
    }

    const data = new FormData();
    data.append('name', document.getElementById('name').value);
    data.append('email', document.getElementById('email').value);
    data.append('phone', document.getElementById('phone').value);
    data.append('password', document.getElementById('password').value);
    data.append('password_confirmation', document.getElementById('password_confirmation').value);
    data.append('status', form.querySelector('input[name="status"]:checked').value);
    data.append('additional_data', quill.root.innerHTML);

    categoryCheckboxes.querySelectorAll('input:checked').forEach(function (cb, i) {
      data.append(`vehicle_category_ids[${i}]`, cb.dataset.categoryId);
    });
    data.append('default_vehicle_id', defaultVehicleSelect.value);

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

    window.gcmBusy.start({ progress: true });

    window.axios
      .post('/api/v1/drivers', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      })
      .then(function () {
        window.location.href = '/app/driver/list?created=1';
      })
      .catch(function (error) {
        window.gcmBusy.stop();
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
