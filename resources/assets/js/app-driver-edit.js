'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const t = window.driverEditTranslations || {};
  const id = window.driverEditId;

  const loading = document.getElementById('driver-edit-loading');
  const formWrapper = document.getElementById('driver-edit-form-wrapper');
  const errorBox = document.getElementById('driver-edit-error');
  const form = document.getElementById('driverEditForm');
  const cancelLink = document.getElementById('driver-edit-cancel');
  const categoryCheckboxes = document.getElementById('vehicle-category-checkboxes');
  const defaultVehicleSelect = document.getElementById('default_vehicle_id');

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

  // Same "checked category(ies) filter which vehicles are offered" logic
  // as the Add page — see that file for the full explanation. Accepts an
  // optional vehicle id to pre-select once the options are loaded (used
  // right after the driver's existing data comes in).
  function refreshVehicleOptions(preselectId) {
    const checked = Array.from(categoryCheckboxes.querySelectorAll('input:checked'));

    if (checked.length === 0) {
      defaultVehicleSelect.innerHTML = '<option value="">' + (t.select_types_first || 'Select vehicle type(s) first') + '</option>';
      defaultVehicleSelect.disabled = true;
      return;
    }

    defaultVehicleSelect.disabled = true;
    defaultVehicleSelect.innerHTML = '<option value="">' + (t.loading || 'Loading...') + '</option>';

    Promise.all(
      checked.map((cb) => window.axios.get('/api/v1/vehicles', {
        params: { category: cb.value, operational_status: 'active', per_page: 200 }
      }))
    )
      .then(function (responses) {
        const byId = new Map();
        responses.forEach((res) => res.data.data.forEach((v) => byId.set(v.id, v)));

        const wanted = preselectId != null ? preselectId : Number(defaultVehicleSelect.value);
        defaultVehicleSelect.innerHTML = '<option value="">' + (t.select_vehicle || 'Select a vehicle') + '</option>';
        byId.forEach(function (v) {
          const opt = document.createElement('option');
          opt.value = v.id;
          opt.textContent = v.plate + (v.category && v.category.name ? ' — ' + v.category.name : '');
          defaultVehicleSelect.appendChild(opt);
        });
        if (byId.has(wanted)) {
          defaultVehicleSelect.value = wanted;
        }
        defaultVehicleSelect.disabled = false;
      })
      .catch(function () {
        defaultVehicleSelect.innerHTML = '<option value="">' + (t.generic_error || 'Something went wrong. Please try again.') + '</option>';
      });
  }

  Promise.all([
    window.axios.get('/api/v1/vehicle-categories'),
    window.axios.get(`/api/v1/drivers/${id}`)
  ])
    .then(function ([categoriesRes, driverRes]) {
      const d = driverRes.data.data;

      categoriesRes.data.data.forEach(function (category) {
        const wrapper = document.createElement('div');
        wrapper.className = 'form-check';
        wrapper.innerHTML =
          '<input class="form-check-input vehicle-category-checkbox" type="checkbox" value="' + category.slug + '" data-category-id="' + category.id + '" id="vc-' + category.slug + '">' +
          '<label class="form-check-label" for="vc-' + category.slug + '">' + category.name + '</label>';
        categoryCheckboxes.appendChild(wrapper);
      });
      categoryCheckboxes.querySelectorAll('input').forEach((cb) => cb.addEventListener('change', function () {
        refreshVehicleOptions();
      }));

      const qualifiedIds = d.qualified_vehicle_category_ids || [];
      categoryCheckboxes.querySelectorAll('input').forEach(function (cb) {
        cb.checked = qualifiedIds.includes(Number(cb.dataset.categoryId));
      });
      if (qualifiedIds.length > 0) {
        refreshVehicleOptions(d.default_vehicle ? d.default_vehicle.id : null);
      }

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

    categoryCheckboxes.querySelectorAll('input:checked').forEach(function (cb, i) {
      data.append(`vehicle_category_ids[${i}]`, cb.dataset.categoryId);
    });
    data.append('default_vehicle_id', defaultVehicleSelect.value);

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
