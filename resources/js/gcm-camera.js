/**
 * A live-camera photo with the date, time and (optionally) the location burnt into the picture
 * (trips, decision ج12: the driver photographs the trip on the spot — there is NO choosing from the gallery).
 *
 *   const photo = await gcmCamera.capture({ location: { lat, lng }, timeZone: 'Asia/Riyadh' });
 *   photo.blob      Blob (image/jpeg)        photo.file   File ('trip-photo-….jpg')
 *   photo.takenAt   Date                     photo.width / photo.height
 *
 * It opens a full-screen camera view (rear camera when there is one), "Take photo" freezes the frame with the
 * stamp on it, then "Use photo" resolves or "Retake" goes back to the live view. There is deliberately no
 * <input type="file"> anywhere in it. (A browser cannot prove a picture came from the camera — the server keeps
 * its own upload time next to the stamp; this component is what makes the honest path the only easy one.)
 *
 * Rejects with a GcmCameraError whose `code` is one of: unsupported, insecure, denied, notfound, busy, failed,
 * cancelled (the user closed the view). `message` is translated (window.gcmI18n.camera_*). The stamp uses
 * the device clock, shown in `timeZone` (default Asia/Riyadh, the system's time zone) with Latin digits.
 */
'use strict';

const MAX_SIDE = 1920;
const QUALITY = 0.85;

function text(key) {
  const i18n = window.gcmI18n || {};

  return i18n[key] || key;
}

class GcmCameraError extends Error {
  constructor(code) {
    super(code === 'cancelled' ? '' : text('camera_' + code));
    this.name = 'GcmCameraError';
    this.code = code;
  }
}

window.GcmCameraError = GcmCameraError;

/** 'YYYY-MM-DD HH:mm:ss' in `timeZone`, Latin digits, 24 h. */
function stampTime(date, timeZone) {
  const parts = {};

  new Intl.DateTimeFormat('en-GB', {
    timeZone: timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23'
  })
    .formatToParts(date)
    .forEach(function (p) {
      parts[p.type] = p.value;
    });

  return parts.year + '-' + parts.month + '-' + parts.day + ' ' + parts.hour + ':' + parts.minute + ':' + parts.second;
}

function mapError(error) {
  switch (error && error.name) {
    case 'NotAllowedError':
    case 'SecurityError':
      return new GcmCameraError('denied');
    case 'NotFoundError':
    case 'OverconstrainedError':
      return new GcmCameraError('notfound');
    case 'NotReadableError':
    case 'AbortError':
      return new GcmCameraError('busy');
    default:
      return new GcmCameraError('failed');
  }
}

function button(label, classes, icon) {
  const b = document.createElement('button');
  b.type = 'button';
  b.className = 'btn ' + classes;
  b.innerHTML = (icon ? '<i class="ti ' + icon + ' me-1"></i>' : '') + '<span></span>';
  b.querySelector('span').textContent = label;

  return b;
}

/** Draws the frame, then the stamp band along its bottom edge. */
function drawStamped(video, takenAt, opts) {
  const sourceW = video.videoWidth;
  const sourceH = video.videoHeight;
  const ratio = Math.min(1, MAX_SIDE / Math.max(sourceW, sourceH));
  const w = Math.round(sourceW * ratio);
  const h = Math.round(sourceH * ratio);

  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, w, h);

  const lines = [stampTime(takenAt, opts.timeZone)];
  const loc = opts.location;
  if (loc && Number.isFinite(Number(loc.lat)) && Number.isFinite(Number(loc.lng))) {
    lines.push(Number(loc.lat).toFixed(6) + ', ' + Number(loc.lng).toFixed(6));
  }

  const size = Math.max(14, Math.round(Math.min(w, h) * 0.034));
  const pad = Math.round(size * 0.6);
  const band = lines.length * size * 1.25 + pad * 2;

  ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
  ctx.fillRect(0, h - band, w, band);
  ctx.fillStyle = '#ffffff';
  ctx.font = '600 ' + size + 'px "Courier New", monospace';
  ctx.textBaseline = 'top';
  ctx.direction = 'ltr';
  ctx.textAlign = 'left';
  lines.forEach(function (line, i) {
    ctx.fillText(line, pad, h - band + pad + i * size * 1.25);
  });

  return canvas;
}

window.gcmCamera = {
  capture: function (options) {
    const opts = Object.assign({ location: null, timeZone: 'Asia/Riyadh' }, options || {});

    return new Promise(function (resolve, reject) {
      if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        // Browsers hide mediaDevices on plain http — tell that apart from a device that has no camera API.
        reject(new GcmCameraError(window.isSecureContext === false ? 'insecure' : 'unsupported'));
        return;
      }

      const overlay = document.createElement('div');
      overlay.className = 'gcm-camera';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.style.cssText =
        'position:fixed;inset:0;z-index:2000;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;';

      const video = document.createElement('video');
      video.setAttribute('playsinline', '');
      video.muted = true;
      video.autoplay = true;
      video.style.cssText = 'flex:1;min-height:0;width:100%;object-fit:contain;background:#000;';

      const still = document.createElement('img');
      still.alt = '';
      still.style.cssText = 'flex:1;min-height:0;width:100%;object-fit:contain;background:#000;display:none;';

      const bar = document.createElement('div');
      bar.style.cssText = 'width:100%;display:flex;gap:.75rem;justify-content:center;align-items:center;padding:1rem;background:#000;';

      const shoot = button(text('camera_take'), 'btn-primary btn-lg', 'ti-camera');
      const retake = button(text('camera_retake'), 'btn-label-secondary', 'ti-refresh');
      const use = button(text('camera_use'), 'btn-success btn-lg', 'ti-check');
      const cancel = button(text('camera_cancel'), 'btn-label-secondary', 'ti-x');
      retake.style.display = 'none';
      use.style.display = 'none';

      bar.append(cancel, shoot, retake, use);
      overlay.append(video, still, bar);
      document.body.appendChild(overlay);
      const previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';

      let stream = null;
      let photo = null;
      let settled = false;

      function stopStream() {
        if (stream) stream.getTracks().forEach((t) => t.stop());
        stream = null;
      }

      function close() {
        stopStream();
        document.removeEventListener('keydown', onKey);
        if (still.src.startsWith('blob:')) URL.revokeObjectURL(still.src);
        overlay.remove();
        document.body.style.overflow = previousOverflow;
      }

      function finish(error, result) {
        if (settled) return;
        settled = true;
        close();
        error ? reject(error) : resolve(result);
      }

      function onKey(event) {
        if (event.key === 'Escape') finish(new GcmCameraError('cancelled'));
      }
      document.addEventListener('keydown', onKey);

      function live() {
        photo = null;
        still.style.display = 'none';
        video.style.display = '';
        shoot.style.display = '';
        retake.style.display = 'none';
        use.style.display = 'none';
        video.play().catch(function () {});
      }

      cancel.addEventListener('click', function () {
        finish(new GcmCameraError('cancelled'));
      });

      shoot.addEventListener('click', function () {
        if (!video.videoWidth) return; // the first frame has not arrived yet

        const takenAt = new Date();
        const canvas = drawStamped(video, takenAt, opts);
        shoot.disabled = true;

        canvas.toBlob(
          function (blob) {
            shoot.disabled = false;
            if (!blob) {
              finish(new GcmCameraError('failed'));
              return;
            }

            photo = {
              blob: blob,
              file: new File([blob], 'trip-photo-' + takenAt.getTime() + '.jpg', { type: 'image/jpeg' }),
              takenAt: takenAt,
              width: canvas.width,
              height: canvas.height
            };

            if (still.src.startsWith('blob:')) URL.revokeObjectURL(still.src);
            still.src = URL.createObjectURL(blob);
            video.pause();
            video.style.display = 'none';
            still.style.display = '';
            shoot.style.display = 'none';
            retake.style.display = '';
            use.style.display = '';
          },
          'image/jpeg',
          QUALITY
        );
      });

      retake.addEventListener('click', live);
      use.addEventListener('click', function () {
        if (photo) finish(null, photo);
      });

      navigator.mediaDevices
        .getUserMedia({
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
          audio: false
        })
        .then(function (s) {
          if (settled) {
            s.getTracks().forEach((t) => t.stop());
            return;
          }
          stream = s;
          video.srcObject = s;
        })
        .catch(function (error) {
          finish(mapError(error));
        });
    });
  }
};
