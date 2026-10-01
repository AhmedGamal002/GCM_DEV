/**
 * Edit Sub-service page — the shared form engine plus the status card
 * (FRD V01.14 §1.9.2: deactivate / re-activate from the edit page).
 * Confirm-checkbox-gates-the-button like the other modules; hidden when the
 * sub-service is locked. Reactivation is refused (422) while its main
 * service is deactivated — the message comes from the server.
 */
'use strict';

import { initSubServiceForm } from './service/sub-form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.subServiceFormTranslations || {};
  const id = window.subServiceEditId;

  const statusCard = document.getElementById('sub-service-status-card');
  const statusForm = document.getElementById('subServiceStatusForm');
  const confirmCheckbox = document.getElementById('ss-confirm');
  const submitBtn = document.getElementById('ss-submit');
  const errorBox = document.getElementById('sub-service-form-error');
  const okBox = document.getElementById('sub-service-status-ok');

  if (new URLSearchParams(window.location.search).get('status_updated')) {
    okBox.textContent = t.status_updated || 'Sub-service status updated successfully.';
    okBox.classList.remove('d-none');
  }

  initSubServiceForm({
    mode: 'edit',
    id: id,
    onLoaded: (sub) => {
      const chosen = statusForm.querySelector(`input[name="new_status"][value="${sub.operational_status}"]`);
      if (chosen) chosen.checked = true;
      if (!sub.locked) statusCard.classList.remove('d-none');
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
      .patch(`/api/v1/sub-services/${id}/status`, { status: checked.value })
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
