/**
 * What the client's trip pages share (part 7.5): the legal confirmation box (FRD §1.14 — before approving and
 * before rejecting), the approve / reject calls behind the e-mailed code (window.gcmWithOtp), and the status
 * badge colours.
 */
'use strict';

export const STATUS_CLASS = {
  awaiting_client_approval: 'bg-label-warning',
  client_approved: 'bg-label-info',
  client_issue: 'bg-label-danger',
  completed: 'bg-label-success',
  cancelled: 'bg-label-secondary'
};

/**
 * Opens #client-trip-confirm (tenant/trips/client/_confirm-modal) with the FRD's message; resolves true when the
 * user confirms, false when they close it.
 */
export function confirmBox({ title, message, note, okLabel, tone }) {
  const el = document.getElementById('client-trip-confirm');
  const modal = window.bootstrap.Modal.getOrCreateInstance(el);
  const ok = el.querySelector('[data-confirm-ok]');
  const box = el.querySelector('[data-confirm-box]');

  el.querySelector('[data-confirm-title]').textContent = title;
  el.querySelector('[data-confirm-message]').textContent = note ? message + ' ' + note : message;
  box.className = 'alert d-flex align-items-start gap-2 mb-0 ' + (tone === 'danger' ? 'alert-danger' : 'alert-warning');
  ok.className = 'btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-success');
  if (okLabel) ok.textContent = okLabel;

  return new Promise((resolve) => {
    let confirmed = false;
    ok.onclick = () => {
      confirmed = true;
      modal.hide();
    };
    el.addEventListener('hidden.bs.modal', () => resolve(confirmed), { once: true });
    modal.show();
  });
}

/**
 * POST /trips/{id}/client-approve | client-reject (multipart) — through the code box when the server asks for
 * it, under the busy overlay. On success the overlay stays (the caller moves to another page); on failure it is
 * lifted and the error passed on (`otpCancelled` when the user closed the code box).
 */
export function submitReview(tripId, action, formData) {
  window.gcmBusy.start();

  return window
    .gcmWithOtp(() => window.axios.post('/api/v1/trips/' + tripId + '/client-' + action, formData), { purpose: 'client_' + action })
    .catch((error) => {
      window.gcmBusy.stop();
      throw error;
    });
}
