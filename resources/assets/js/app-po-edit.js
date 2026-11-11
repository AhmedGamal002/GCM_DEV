/**
 * Edit Purchase Order page — the shared purchase-order form engine plus the status card (FRD V01.14 §1.13:
 * deactivate / re-activate from the edit page, the confirm checkbox gating the button like the other modules).
 * The status change is its own request and is allowed with or without trips on the PO.
 */
'use strict';

import { initPoForm } from './po/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  const t = window.poFormTranslations || {};
  const id = window.poEditId;

  const statusCard = document.getElementById('po-status-card');
  const statusForm = document.getElementById('poStatusForm');
  const confirmCheckbox = document.getElementById('ps-confirm');
  const submitBtn = document.getElementById('ps-submit');
  const errorBox = document.getElementById('po-form-error');
  const okBox = document.getElementById('po-status-ok');

  if (new URLSearchParams(window.location.search).get('status_updated')) {
    okBox.textContent = t.status_updated || 'Purchase order status updated successfully.';
    okBox.classList.remove('d-none');
  }

  initPoForm({
    mode: 'edit',
    id: id,
    onLoaded: (po) => {
      const chosen = statusForm.querySelector(`input[name="new_status"][value="${po.operational_status}"]`);
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
      .patch(`/api/v1/purchase-orders/${id}/status`, { status: checked.value })
      .then(() => {
        window.location.href = `${window.location.pathname}?status_updated=1`;
      })
      .catch((error) => {
        window.gcmBusy.stop();
        // A re-activation refused by the overlap rule comes back as a 422 naming `status`.
        const data = error.response && error.response.data;
        errorBox.textContent = (data && data.errors && data.errors.status && data.errors.status[0]) || (data && data.message) || t.generic_error;
        errorBox.classList.remove('d-none');
        window.scrollTo(0, 0);
      });
  });
});
