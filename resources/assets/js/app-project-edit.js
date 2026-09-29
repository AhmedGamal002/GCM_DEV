/**
 * Edit Project page — the shared project form engine plus the status card
 * (FRD V01.14 §1.12.4: deactivate / re-activate from the edit page,
 * confirm-checkbox-gates-the-button like the other modules).
 */
'use strict';

import { initProjectForm } from './project/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.projectFormTranslations || {};
  const id = window.projectEditId;

  const statusCard = document.getElementById('project-status-card');
  const statusForm = document.getElementById('projectStatusForm');
  const confirmCheckbox = document.getElementById('ps-confirm');
  const submitBtn = document.getElementById('ps-submit');
  const errorBox = document.getElementById('project-form-error');
  const okBox = document.getElementById('project-status-ok');

  if (new URLSearchParams(window.location.search).get('status_updated')) {
    okBox.textContent = t.status_updated || 'Project status updated successfully.';
    okBox.classList.remove('d-none');
  }

  initProjectForm({
    mode: 'edit',
    id: id,
    onLoaded: (project) => {
      const chosen = statusForm.querySelector(`input[name="new_status"][value="${project.operational_status}"]`);
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
      .patch(`/api/v1/projects/${id}/status`, { status: checked.value })
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
