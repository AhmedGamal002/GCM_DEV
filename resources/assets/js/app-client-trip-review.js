/**
 * The client's approve / reject page (FRD V01.14 §1.14 "تعديل بيانات الرحلة" from the client's side, part 7.5).
 *
 * The FRD form: the main service (read-only), the sub-service (only the ones that suit the trip's container —
 * GET /trips/{id}/client-sub-services), the signature (required: the saved one, or drawn now with "save it as my
 * signature" — decision د6), the operational stamp (optional: the saved one is used unless another file is
 * chosen, for this trip only), an attachment and a comment. "Approve" and "Reject" each open the FRD's
 * confirmation text, then go through the e-mailed code (gcmWithOtp). Rejecting needs the reasons (ج8) and
 * doesn't send the sub-service (ج7: not kept on reject).
 */
'use strict';

import { confirmBox, submitReview } from './client-trip/review-core.js';

$(function () {
  const t = window.clientTripReviewTranslations || {};
  const id = window.clientTripReviewId;
  const saved = window.clientTripSaved || {};
  const $id = (x) => document.getElementById(x);
  const form = $id('clientTripReviewForm');
  const sub = $id('ctr-sub-service');
  let currentSubId = null;
  let pad = null;

  const showError = (message) => {
    $id('ctr-error').textContent = message || '';
    $id('ctr-error').classList.toggle('d-none', !message);
    if (message) window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const fieldError = (el, message) => {
    el.classList.toggle('is-invalid', !!message);
    const fb = el.parentElement.querySelector('.invalid-feedback');
    if (fb) fb.textContent = message || '';
  };

  const clearErrors = () => {
    showError('');
    form.querySelectorAll('.is-invalid').forEach((el) => fieldError(el, ''));
    $id('ctr-signature-error').classList.add('d-none');
  };

  // ---------------------------------------------------------------- signature: saved or drawn now

  const sigSaved = $id('ctr-sig-saved');
  const sigDraw = $id('ctr-sig-draw');

  const showSignatureMode = () => {
    const drawing = sigDraw.checked;
    $id('ctr-sig-saved-box').classList.toggle('d-none', drawing);
    $id('ctr-sig-draw-box').classList.toggle('d-none', !drawing);
    if (drawing && !pad) pad = window.gcmSignaturePad($id('ctr-signature-pad'), { height: 200 });
  };

  if (saved.signature) {
    $id('ctr-sig-saved-img').src = saved.signature;
    sigSaved.checked = true;
  } else {
    // Nothing saved yet: only "sign now".
    sigSaved.closest('.form-check').classList.add('d-none');
    sigDraw.checked = true;
  }
  [sigSaved, sigDraw].forEach((r) => r.addEventListener('change', showSignatureMode));
  $id('ctr-signature-clear').addEventListener('click', () => pad && pad.clear());

  if (saved.stamp) {
    $id('ctr-stamp-saved-img').src = saved.stamp;
    $id('ctr-stamp-saved-box').classList.remove('d-none');
  }

  // ---------------------------------------------------------------- load the trip and the sub-services

  const render = (trip) => {
    if (!trip.client_actions || !trip.client_actions.approve) {
      showError(t.not_awaiting);
      $id('ctr-loading').classList.add('d-none');
      return false;
    }

    $id('ctr-code').textContent = trip.code;
    $id('ctr-date').textContent = trip.trip_at ? moment(trip.trip_at).format('YYYY-MM-DD HH:mm') : '—';
    $id('ctr-project').textContent = trip.project ? trip.project.name : '—';
    $id('ctr-driver').textContent = trip.driver ? trip.driver.name : '—';
    $id('ctr-vehicle').textContent = trip.vehicle ? trip.vehicle.plate : '—';
    $id('ctr-quantity').textContent =
      [trip.quantity_ton !== null ? trip.quantity_ton + ' ' + t.ton : null, trip.quantity_cbm !== null ? trip.quantity_cbm + ' ' + t.cbm : null]
        .filter(Boolean)
        .join(' / ') || '—';
    $id('ctr-main-service').value = trip.main_service ? trip.main_service.name : '';
    currentSubId = trip.sub_service ? String(trip.sub_service.id) : null;

    return true;
  };

  window.axios
    .get('/api/v1/trips/' + id)
    .then((response) => {
      if (!render(response.data.data)) return;

      return window.axios.get('/api/v1/trips/' + id + '/client-sub-services').then((response) => {
        const subs = response.data.data || [];
        sub.innerHTML = '';
        subs.forEach((s) => sub.append(new Option(s.name, s.id, false, String(s.id) === currentSubId)));
        window.refreshGcmSelect(sub);

        $id('ctr-loading').classList.add('d-none');
        form.classList.remove('d-none');
        showSignatureMode();
      });
    })
    .catch((error) => {
      $id('ctr-loading').classList.add('d-none');
      showError(error.response.data.message);
    });

  // ---------------------------------------------------------------- approve / reject

  const fileTooLarge = () => {
    const message = window.gcmFileGuard(form, window.clientTripLimits || { default: 5120 });
    if (message) showError(message);
    return !!message;
  };

  const afterError = (error) => {
    if (error.otpCancelled) return;
    const errors = (error.response && error.response.data && error.response.data.errors) || {};
    const fields = { sub_service_id: sub, comment: $id('ctr-comment'), reason: $id('ctr-comment'), attachment: $id('ctr-attachment'), stamp: $id('ctr-stamp') };
    Object.entries(errors).forEach(([key, messages]) => {
      if (fields[key]) fieldError(fields[key], messages[0]);
      if (key === 'signature') {
        $id('ctr-signature-error').textContent = messages[0];
        $id('ctr-signature-error').classList.remove('d-none');
      }
    });
    showError(error.response.data.message);
  };

  const done = (outcome) => window.location.assign(window.clientTripViewUrl + '?done=' + outcome);

  $id('ctr-approve').addEventListener('click', async function () {
    clearErrors();

    const drawing = sigDraw.checked;
    if (drawing && (!pad || pad.isEmpty())) {
      $id('ctr-signature-error').textContent = t.sign_first;
      $id('ctr-signature-error').classList.remove('d-none');
      return;
    }
    if (fileTooLarge()) return;

    // The signature drawn on the pad is turned into its PNG before the confirmation, so a pad problem shows first.
    let drawn = null;
    if (drawing) {
      try {
        drawn = await pad.toFile('signature.png');
      } catch (e) {
        $id('ctr-signature-error').textContent = e.message;
        $id('ctr-signature-error').classList.remove('d-none');
        return;
      }
    }

    if (!(await confirmBox({ title: t.approve_title, message: t.approve_message, okLabel: t.approve_title }))) return;

    const data = new FormData();
    if (sub.value && sub.value !== currentSubId) data.append('sub_service_id', sub.value);
    if ($id('ctr-comment').value.trim()) data.append('comment', $id('ctr-comment').value.trim());
    if ($id('ctr-attachment').files[0]) data.append('attachment', $id('ctr-attachment').files[0]);
    if ($id('ctr-stamp').files[0]) data.append('stamp', $id('ctr-stamp').files[0]);
    if (drawn) {
      data.append('signature', drawn);
      if ($id('ctr-save-signature').checked) data.append('save_signature', '1');
    } else {
      data.append('use_saved_signature', '1');
    }

    submitReview(id, 'approve', data).then(() => done('approved')).catch(afterError);
  });

  $id('ctr-reject').addEventListener('click', async function () {
    clearErrors();

    const reason = $id('ctr-comment').value.trim();
    if (!reason) {
      fieldError($id('ctr-comment'), t.reason_required);
      $id('ctr-comment').focus();
      return;
    }
    if (fileTooLarge()) return;

    if (!(await confirmBox({ title: t.reject_title, message: t.reject_message, okLabel: t.reject_title, tone: 'danger' }))) return;

    const data = new FormData();
    data.append('reason', reason);
    if ($id('ctr-attachment').files[0]) data.append('attachment', $id('ctr-attachment').files[0]);

    submitReview(id, 'reject', data).then(() => done('rejected')).catch(afterError);
  });
});
