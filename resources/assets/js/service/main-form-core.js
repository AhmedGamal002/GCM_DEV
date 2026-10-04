/**
 * Shared engine for the create & edit main-service forms (both render
 * `tenant.main-services._form`). The supported vehicle categories come
 * from GET /api/v1/vehicle-categories — never a fixed list. On edit the
 * type of service is shown disabled (locked after creation), and the whole
 * form turns read-only when a PO includes the service (`in_use`).
 */
'use strict';

import { createFormErrors, renderCheckboxes, checkedValues, setLastUpdated } from './form-helpers.js';

export function initMainServiceForm(opts) {
  const mode = opts.mode; // 'create' | 'edit'
  const id = opts.id || null;
  const onLoaded = opts.onLoaded || (() => {});
  const t = window.mainServiceFormTranslations || {};

  const form = document.getElementById('mainServiceForm');
  if (!form) return;

  const errorBox = document.getElementById('main-service-form-error');
  const loading = document.getElementById('main-service-form-loading');
  const submitBtn = form.querySelector('button[type="submit"]');
  const categoriesBox = document.getElementById('vehicle-categories-box');
  const errors = createFormErrors(form, errorBox, t);

  const quill = new Quill(document.getElementById('additional-data-editor'), { theme: 'snow', placeholder: '' });

  function renderCategories(categories, checked) {
    renderCheckboxes(
      categoriesBox,
      'vehicle_category_ids',
      categories.map((c) => ({ value: c.id, label: c.name })),
      checked,
      t.no_vehicle_categories
    );
  }

  // ----------------------------------------------------- client validation
  function validate() {
    errors.clearAll();
    let first = null;
    const fail = (el, msg) => {
      errors.show(el, msg);
      if (!first) first = el;
    };

    const name = form.querySelector('#name');
    if (!name.value.trim()) fail(name, t.required);

    if (!form.querySelector('input[name="service_type"]:checked')) {
      fail(form.querySelector('input[name="service_type"]'), t.required);
    }
    if (!checkedValues(form, 'vehicle_category_ids').length) {
      fail(errors.targetFor('vehicle_category_ids'), t.choose_one_category);
    }

    if (first && first.scrollIntoView) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return !first;
  }

  // ---------------------------------------------------------------- submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;

    const data = {
      name: form.querySelector('#name').value.trim(),
      vehicle_category_ids: checkedValues(form, 'vehicle_category_ids'),
      additional_data: quill.getLength() > 1 ? quill.root.innerHTML : null
    };
    if (mode === 'create') {
      data.service_type = form.querySelector('input[name="service_type"]:checked').value;
      data.operational_status = form.querySelector('input[name="operational_status"]:checked').value;
    }

    submitBtn.disabled = true;
    window.gcmBusy.start();

    const request = mode === 'edit' ? window.axios.patch(`/api/v1/main-services/${id}`, data) : window.axios.post('/api/v1/main-services', data);

    request
      .then((res) => {
        if (mode === 'edit') {
          window.location.href = `${t.view_url_base || '/app/main-service/view'}/${id}?saved=1`;
        } else {
          window.location.href = `${t.view_url_base || '/app/main-service/view'}/${res.data.data.id}?created=1`;
        }
      })
      .catch((error) => {
        window.gcmBusy.stop();
        submitBtn.disabled = false;
        errors.handleFailure(error);
      });
  });

  // --------------------------------------------------------- mode bootstrap
  const categoriesRequest = window.axios.get('/api/v1/vehicle-categories').then((res) => res.data.data);

  if (mode === 'create') {
    categoriesRequest
      .then((categories) => renderCategories(categories, []))
      .catch((error) => errors.showMessage(error.response && error.response.data && error.response.data.message));
    return;
  }

  const cancel = document.getElementById('main-service-form-cancel');
  if (cancel) {
    cancel.href = `${t.view_url_base || '/app/main-service/view'}/${id}`;
    if (t.cancel) cancel.textContent = t.cancel;
  }

  Promise.all([window.axios.get(`/api/v1/main-services/${id}`), categoriesRequest])
    .then(([res, categories]) => {
      const service = res.data.data;

      form.querySelector('#name').value = service.name || '';
      const type = form.querySelector(`input[name="service_type"][value="${service.service_type}"]`);
      if (type) type.checked = true;
      quill.root.innerHTML = service.additional_data || '';
      renderCategories(
        categories,
        (service.vehicle_categories || []).map((c) => c.id)
      );
      setLastUpdated('main-service-last-updated', service, t);

      if (service.in_use) {
        document.getElementById('main-service-locked').classList.remove('d-none');
        form.querySelectorAll('input, button[type="submit"]').forEach((el) => (el.disabled = true));
        quill.enable(false);
      }

      loading.classList.add('d-none');
      form.classList.remove('d-none');
      onLoaded(service);
    })
    .catch((error) => {
      loading.classList.add('d-none');
      errors.showMessage(error.response && error.response.data && error.response.data.message);
    });
}
