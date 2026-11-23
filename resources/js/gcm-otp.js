/**
 * The "enter the code we e-mailed you" box (FRD V01.14 "ايميل OTP", parts 7.3 / 7.5) — shared by every page
 * whose action sits behind the server's `otp` middleware (the client's approve / reject; GCM's final approval,
 * rejecting the objection and cancelling).
 *
 *   gcmWithOtp(() => axios.post(url, data), { purpose: 'client_approve' })
 *     → the request's promise. When the server answers 403 `otp_required`, the box opens, e-mails a code
 *       (POST /api/v1/otp/send), takes it (POST /api/v1/otp/verify) and repeats the request once. The FRD asks
 *       for it "once per session": after a verified code the server lets the session through for a while, so
 *       the next action goes straight on.
 *     → rejects with the request's own error, or with an Error whose `otpCancelled` is true when the user
 *       closes the box — the page then stops its busy overlay and shows nothing.
 *
 * While the box is open the page's busy overlay (window.gcmBusy) is lifted, and put back for the retry.
 * The texts are window.gcmI18n.otp_*; the server's messages (wrong / expired code, wait before a new one) are
 * shown as they come (gcm-errors.js already made them readable).
 */
'use strict';

function text(key, fallback) {
  const i18n = window.gcmI18n || {};

  return i18n[key] || fallback;
}

function isOtpRequired(error) {
  const res = error && error.response;

  return !!res && res.status === 403 && res.data && res.data.code === 'otp_required';
}

let modalEl = null;

function build() {
  if (modalEl) return modalEl;

  modalEl = document.createElement('div');
  modalEl.className = 'modal fade';
  modalEl.id = 'gcm-otp-modal';
  modalEl.tabIndex = -1;
  modalEl.setAttribute('aria-hidden', 'true');
  modalEl.setAttribute('data-bs-backdrop', 'static');
  modalEl.innerHTML =
    '<div class="modal-dialog modal-dialog-centered">' +
    '<div class="modal-content">' +
    '<div class="modal-header"><h5 class="modal-title"></h5>' +
    '<button type="button" class="btn-close" data-otp-cancel aria-label="Close"></button></div>' +
    '<form class="modal-body" novalidate>' +
    '<p class="mb-4" data-otp-intro></p>' +
    '<input type="text" class="form-control form-control-lg text-center" dir="ltr" inputmode="numeric" autocomplete="one-time-code" maxlength="12" data-otp-code>' +
    '<div class="text-danger small mt-2 d-none" data-otp-error></div>' +
    '<div class="d-flex justify-content-between align-items-center mt-4">' +
    '<button type="button" class="btn btn-link px-0" data-otp-resend></button>' +
    '<div class="d-flex gap-2">' +
    '<button type="button" class="btn btn-label-secondary" data-otp-cancel></button>' +
    '<button type="submit" class="btn btn-primary" data-otp-verify></button>' +
    '</div></div>' +
    '</form></div></div>';
  document.body.appendChild(modalEl);

  modalEl.querySelector('.modal-title').textContent = text('otp_title', 'Verification code');
  modalEl.querySelector('[data-otp-verify]').textContent = text('otp_verify', 'Confirm');
  modalEl.querySelectorAll('[data-otp-cancel]')[1].textContent = text('otp_cancel', 'Cancel');

  return modalEl;
}

/** Opens the box; resolves once the code is accepted, rejects (otpCancelled) when the user closes it. */
function prompt(purpose) {
  const el = build();
  const modal = window.bootstrap.Modal.getOrCreateInstance(el);
  const form = el.querySelector('form');
  const input = el.querySelector('[data-otp-code]');
  const intro = el.querySelector('[data-otp-intro]');
  const errorBox = el.querySelector('[data-otp-error]');
  const resend = el.querySelector('[data-otp-resend]');
  const verify = el.querySelector('[data-otp-verify]');
  let timer = null;

  const showError = (message) => {
    errorBox.textContent = message || '';
    errorBox.classList.toggle('d-none', !message);
  };

  const countdown = (seconds) => {
    clearInterval(timer);
    let left = Math.max(0, Number(seconds) || 0);
    const paint = () => {
      resend.disabled = left > 0;
      resend.textContent = left > 0 ? text('otp_resend_in', 'Send a new code in :s s').replace(':s', left) : text('otp_resend', 'Send a new code');
    };
    paint();
    timer = setInterval(() => {
      left -= 1;
      paint();
      if (left <= 0) clearInterval(timer);
    }, 1000);
  };

  const send = () => {
    resend.disabled = true;
    showError('');

    return window.axios
      .post('/api/v1/otp/send', { purpose: purpose || null })
      .then((response) => {
        const data = response.data.data || {};
        intro.textContent = text('otp_sent', 'We e-mailed a code to :email. Enter it to continue.').replace(':email', data.sent_to || '');
        countdown(data.resend_after_seconds || 60);
      })
      .catch((error) => {
        // "Wait before asking again" still leaves the last code usable — keep the box open.
        intro.textContent = text('otp_enter', 'Enter the code we e-mailed you.');
        showError(error.response && error.response.data ? error.response.data.message : window.gcmErrorMessage(error));
        countdown(60);
      });
  };

  return new Promise((resolve, reject) => {
    let done = false;

    const finish = (ok) => {
      if (done) return;
      done = true;
      clearInterval(timer);
      form.onsubmit = null;
      resend.onclick = null;
      el.querySelectorAll('[data-otp-cancel]').forEach((b) => (b.onclick = null));
      modal.hide();
      if (ok) {
        resolve();
      } else {
        const cancelled = new Error('cancelled');
        cancelled.otpCancelled = true;
        reject(cancelled);
      }
    };

    form.onsubmit = (e) => {
      e.preventDefault();
      const code = input.value.replace(/\s+/g, '');
      if (!code) {
        showError(text('otp_enter', 'Enter the code we e-mailed you.'));
        return;
      }
      verify.disabled = true;
      showError('');
      window.axios
        .post('/api/v1/otp/verify', { code: code })
        .then(() => finish(true))
        .catch((error) => {
          verify.disabled = false;
          showError(error.response && error.response.data ? error.response.data.message : window.gcmErrorMessage(error));
          input.select();
        });
    };
    resend.onclick = () => send();
    el.querySelectorAll('[data-otp-cancel]').forEach((b) => (b.onclick = () => finish(false)));

    input.value = '';
    verify.disabled = false;
    intro.textContent = text('otp_sending', 'Sending a code to your e-mail…');
    showError('');
    modal.show();
    el.addEventListener('shown.bs.modal', () => input.focus(), { once: true });
    send();
  });
}

window.gcmWithOtp = function (request, options) {
  const opts = options || {};

  return request().catch((error) => {
    if (!isOtpRequired(error)) throw error;

    if (window.gcmBusy) window.gcmBusy.stop();

    return prompt(opts.purpose).then(() => {
      if (window.gcmBusy) window.gcmBusy.start();

      return request();
    });
  });
};
