/**
 * Edit Main Service page — the shared form engine plus the status card
 * (FRD V01.14 §1.9.1: deactivate / re-activate from the edit page;
 * deactivating also deactivates every sub-service under it, so the card
 * warns about that). Confirm-checkbox-gates-the-button like the other
 * modules; hidden when the service is locked (in a PO).
 */
'use strict';

import { initMainServiceForm } from './service/main-form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.mainServiceFormTranslations || {};
  const id = window.mainServiceEditId;

  const statusCard = document.getElementById('main-service-status-card');
  const statusForm = document.getElementById('mainServiceStatusForm');
  const confirmCheckbox = document.getElementById('ms-confirm');
  const submitBtn = document.getElementById('ms-submit');
  const errorBox = document.getElementById('main-service-form-error');
  const okBox = document.getElementById('main-service-status-ok');

  if (new URLSearchParams(window.location.search).get('status_updated')) {
    okBox.textContent = t.status_updated || 'Main service status updated successfully.';
    okBox.classList.remove('d-none');
  }

  initMainServiceForm({
    mode: 'edit',
    id: id,
    onLoaded: (service) => {
      const chosen = statusForm.querySelector(`input[name="new_status"][value="${service.operational_status}"]`);
      if (chosen) chosen.checked = true;
      if (!service.in_use) statusCard.classList.remove('d-none');
    }
  });

  confirmCheckbox.addEventListener('change', () => {
    submitBtn.disabled = !confirmCheckbox.checked;
  });

  statusForm.addEventListener('submit', (e) => {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const checked = statusForm.querySelector('input[name="new_status"]:checked');
    if (!checked) return;

    window.gcmBusy.start();

    window.axios
      .patch(`/api/v1/main-services/${id}/status`, { status: checked.value })
      .then(() => {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch((error) => {
        window.gcmBusy.stop();
        errorBox.textContent = (error.response && error.response.data && error.response.data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
