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
  window.initGcmSelects(form);

  // FRD §1.3: belongs to GCM or to a contractor (pick one from the list).
  const affiliation = window.gcmAffiliation(form);

  cancelLink.href = t.view_url_base + '/' + id;

  const permitsList = document.getElementById('entry-permits-list');
  const permitsEmpty = document.getElementById('entry-permits-empty');
  const permitTemplate = document.getElementById('entry-permit-row-template');

  const syncPermitsEmpty = () => permitsEmpty.classList.toggle('d-none', permitsList.children.length > 0);

  // One row per permit; `permit` is the saved record (null for a blank new row). A saved permit keeps
  // its hidden id and a link to its current file — leaving the file input empty keeps that file.
  function addPermitRow(permit) {
    const row = permitTemplate.content.cloneNode(true);
    const root = row.querySelector('.entry-permit-row');

    if (permit) {
      root.querySelector('.entry-id').value = permit.id;
      root.querySelector('.entry-area-name').value = permit.area_name || '';
      root.querySelector('.entry-permit-number').value = permit.permit_number || '';
      root.querySelector('.entry-valid-to').value = permit.valid_to || '';
      if (permit.has_attachment) {
        const hint = root.querySelector('.entry-current');
        hint.innerHTML = window.gcmViewAttachment('/api/v1/driver-entry-permits/' + permit.id + '/attachment');
        hint.classList.remove('d-none');
      }
    }

    root.querySelector('.remove-entry-permit').addEventListener('click', function () {
      root.remove();
      syncPermitsEmpty();
    });
    permitsList.appendChild(row);
    syncPermitsEmpty();
  }

  document.getElementById('add-entry-permit').addEventListener('click', function () {
    addPermitRow(null);
  });

  const quill = new Quill(document.getElementById('additional-data-editor'), {
    theme: 'snow',
    placeholder: ''
  });

  function showError(message) {
    errorBox.textContent = message || (window.gcmI18n && window.gcmI18n.unknown);
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
      window.refreshGcmSelect(defaultVehicleSelect);
      return;
    }

    defaultVehicleSelect.disabled = true;
    defaultVehicleSelect.innerHTML = '<option value="">' + (t.loading || 'Loading...') + '</option>';
    window.refreshGcmSelect(defaultVehicleSelect);

    Promise.all(
      checked.map((cb) => window.axios.get('/api/v1/vehicles', {
        // unassigned_as_default hides vehicles already taken as another
        // driver's default; exclude_default_of_driver keeps THIS driver's
        // own currently-assigned vehicle visible (it's "taken", but by
        // themselves — a re-submit without changing it must still work).
        params: {
          category: cb.value,
          operational_status: 'active',
          per_page: 200,
          unassigned_as_default: 1,
          exclude_default_of_driver: id
        }
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
        window.refreshGcmSelect(defaultVehicleSelect);
      })
      .catch(function (error) {
        defaultVehicleSelect.innerHTML = '<option value="">' + window.gcmErrorMessage(error) + '</option>';
        window.refreshGcmSelect(defaultVehicleSelect);
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
          '<label class="form-check-label" for="vc-' + category.slug + '">' + $('<div>').text(category.name).html() + '</label>';
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
      // Without this the edit would post the default "GCM" and move a contractor's driver.
      affiliation.set(d.affiliation, d.contractor);

      if (d.updated_by_name && t.last_updated_by) {
        const at = d.updated_at ? new Date(d.updated_at).toLocaleString() : '';
        document.getElementById('de-updated-by').textContent = t.last_updated_by
          .replace(':name', d.updated_by_name)
          .replace(':at', at);
      }

      document.getElementById('residence_number').value = d.residence.number || '';
      document.getElementById('residence_valid_to').value = d.residence.valid_to || '';

      document.getElementById('license_number').value = d.license.number || '';
      document.getElementById('license_valid_to').value = d.license.valid_to || '';

      document.getElementById('operational_license_number').value = d.operational_license.number || '';
      document.getElementById('operational_license_valid_to').value = d.operational_license.valid_to || '';

      document.getElementById('insurance_number').value = d.insurance.number || '';
      document.getElementById('insurance_valid_to').value = d.insurance.valid_to || '';

      (d.entry_permits || []).forEach(addPermitRow);
      syncPermitsEmpty();

      // The files already on record — same idea as the vehicle edit page: a link to the
      // current one next to its input (leaving the input empty keeps it).
      if (d.photo_url) {
        const el = document.getElementById('photo_current');
        el.innerHTML = '<a href="' + d.photo_url + '" target="_blank">' + $('<div>').text(d.photo_url.split('/').pop()).html() + '</a>';
        el.classList.remove('d-none');
      }
      [
        ['residence', d.residence, 'residence'],
        ['license', d.license, 'license'],
        ['operational_license', d.operational_license, 'operational-license'],
        ['insurance', d.insurance, 'insurance']
      ].forEach(function (doc) {
        if (!doc[1] || !doc[1].has_attachment) return;
        const el = document.getElementById(doc[0] + '_current');
        el.innerHTML = window.gcmViewAttachment('/api/v1/drivers/' + id + '/documents/' + doc[2]);
        el.classList.remove('d-none');
      });

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
    data.append('additional_data', quill.root.innerHTML);
    const aff = affiliation.get();
    data.append('affiliation', aff.affiliation);
    if (aff.contractor_id) data.append('contractor_id', aff.contractor_id);
    data.append('_method', 'PATCH');

    const newPassword = document.getElementById('password').value;
    if (newPassword) {
      data.append('password', newPassword);
      data.append('password_confirmation', document.getElementById('password_confirmation').value);
    }

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

    // The whole permit list is sent (matched by id on the server; a permit that is not in it is
    // removed). The flag makes "every permit removed" work — a multipart form can't send an empty array.
    data.append('sync_entry_permits', '1');
    permitsList.querySelectorAll('.entry-permit-row').forEach(function (row, i) {
      const permitId = row.querySelector('.entry-id').value;
      if (permitId) data.append(`entry_permits[${i}][id]`, permitId);
      data.append(`entry_permits[${i}][area_name]`, row.querySelector('.entry-area-name').value);
      data.append(`entry_permits[${i}][permit_number]`, row.querySelector('.entry-permit-number').value);
      data.append(`entry_permits[${i}][valid_to]`, row.querySelector('.entry-valid-to').value);
      const file = row.querySelector('.entry-attachment').files[0];
      if (file) data.append(`entry_permits[${i}][attachment]`, file);
    });

    window.gcmBusy.start({ progress: true });

    window.axios
      .post(`/api/v1/drivers/${id}`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: window.gcmBusy.onUploadProgress
      })
      .then(function () {
        window.location.href = `${t.view_url_base}/${id}?saved=1`;
      })
      .catch(function (error) {
        window.gcmBusy.stop();
        const message =
          error.response && error.response.data && error.response.data.errors
            ? Object.values(error.response.data.errors).flat().join(' ')
            : error.response && error.response.data && error.response.data.message;

        showError(message);
      });
  });
});
