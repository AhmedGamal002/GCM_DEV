/**
 * Edit Client Company page — the shared company form engine plus the
 * status card (FRD V01.14 §1.11.4: deactivate / re-activate from the edit
 * page, confirm-checkbox-gates-the-button like the other modules).
 */
'use strict';

import { initCompanyForm } from './company/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.companyFormTranslations || {};
  const id = window.companyEditId;

  const statusCard = document.getElementById('company-status-card');
  const statusForm = document.getElementById('companyStatusForm');
  const confirmCheckbox = document.getElementById('cs-confirm');
  const submitBtn = document.getElementById('cs-submit');
  const errorBox = document.getElementById('company-form-error');
  const okBox = document.getElementById('company-status-ok');

  if (new URLSearchParams(window.location.search).get('status_updated')) {
    okBox.textContent = t.status_updated || 'Client company status updated successfully.';
    okBox.classList.remove('d-none');
  }

  initCompanyForm({
    mode: 'edit',
    id: id,
    onLoaded: (company) => {
      const chosen = statusForm.querySelector(`input[name="new_status"][value="${company.operational_status}"]`);
      if (chosen) chosen.checked = true;
      statusCard.classList.remove('d-none');
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
      .patch(`/api/v1/companies/${id}/status`, { status: checked.value })
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
