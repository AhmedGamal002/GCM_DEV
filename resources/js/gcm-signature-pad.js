/**
 * A drawing pad for a hand signature (trips: the client's approval 7.5, and GCM's final approval, decision د5).
 *
 *   const pad = gcmSignaturePad(containerElement, { height: 200, ink: '#0b1f3a', onChange: (empty) => … });
 *   pad.isEmpty()                 → boolean
 *   pad.clear()
 *   await pad.toBlob()            → Blob (image/png, transparent background, cropped to the strokes)
 *   await pad.toFile('signature.png') → File
 *   pad.toDataURL()               → 'data:image/png;base64,…'
 *   pad.destroy()
 *
 * The PNG is what the server receives; the server re-checks the type and the size (≤ 2 MB), so the limits here
 * are for the user's comfort, not for security. Strokes live in a fixed logical space (600 × `height`), so the
 * drawing keeps its proportions when the box is resized or the phone is rotated, and the exported image is the
 * same on every screen. Drawn with pointer events (mouse, pen and finger) and `touch-action: none`, so the page
 * does not scroll while signing.
 *
 * toBlob() rejects with an Error whose `code` is `empty` (nothing drawn) or `too_large` (still over the limit
 * after shrinking); `message` is translated (window.gcmI18n.signature_*).
 */
'use strict';

const LOGICAL_WIDTH = 600;
const MAX_BYTES = 2 * 1024 * 1024;
const PADDING = 8;

function text(key) {
  const i18n = window.gcmI18n || {};

  return i18n[key] || key;
}

function fail(code) {
  const error = new Error(text('signature_' + code));
  error.code = code;

  return error;
}

window.gcmSignaturePad = function (container, options) {
  const opts = Object.assign({ height: 200, ink: '#0b1f3a', lineWidth: 2.6, onChange: null }, options || {});
  const logicalHeight = Math.max(80, Number(opts.height) || 200);
  const strokes = []; // each stroke = array of {x, y} in logical units
  let current = null;
  let activePointer = null;

  container.classList.add('gcm-signature-pad');
  container.style.position = 'relative';
  container.style.width = '100%';
  container.style.aspectRatio = LOGICAL_WIDTH + ' / ' + logicalHeight;
  container.style.background = '#ffffff';
  container.style.border = '1px dashed #9aa0b4';
  container.style.borderRadius = '0.5rem';
  container.style.overflow = 'hidden';
  container.style.userSelect = 'none';

  const hint = document.createElement('div');
  hint.textContent = text('signature_hint');
  hint.style.cssText =
    'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#a1a5b7;pointer-events:none;font-size:1.1rem;';

  const canvas = document.createElement('canvas');
  canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:crosshair;';
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', text('signature_hint'));

  container.appendChild(hint);
  container.appendChild(canvas);

  const ctx = canvas.getContext('2d');

  function scale() {
    return Math.min(2, window.devicePixelRatio || 1) * (canvas.clientWidth / LOGICAL_WIDTH || 1);
  }

  function resizeBackingStore() {
    const s = scale();
    canvas.width = Math.max(1, Math.round(LOGICAL_WIDTH * s));
    canvas.height = Math.max(1, Math.round(logicalHeight * s));
    redraw();
  }

  function strokeStyle(target, s) {
    target.lineCap = 'round';
    target.lineJoin = 'round';
    target.strokeStyle = opts.ink;
    target.fillStyle = opts.ink;
    target.lineWidth = opts.lineWidth * s;
  }

  /** Draws every stroke on `target`, scaled by `s` and shifted by (-ox, -oy) logical units. */
  function paint(target, s, ox, oy) {
    strokeStyle(target, s);

    strokes.forEach(function (points) {
      if (points.length === 1) {
        target.beginPath();
        target.arc((points[0].x - ox) * s, (points[0].y - oy) * s, (opts.lineWidth * s) / 2, 0, Math.PI * 2);
        target.fill();

        return;
      }

      target.beginPath();
      target.moveTo((points[0].x - ox) * s, (points[0].y - oy) * s);

      for (let i = 1; i < points.length - 1; i++) {
        const midX = (points[i].x + points[i + 1].x) / 2;
        const midY = (points[i].y + points[i + 1].y) / 2;
        target.quadraticCurveTo((points[i].x - ox) * s, (points[i].y - oy) * s, (midX - ox) * s, (midY - oy) * s);
      }

      const last = points[points.length - 1];
      target.lineTo((last.x - ox) * s, (last.y - oy) * s);
      target.stroke();
    });
  }

  function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    paint(ctx, canvas.width / LOGICAL_WIDTH, 0, 0);
  }

  function point(event) {
    const box = canvas.getBoundingClientRect();

    return {
      x: ((event.clientX - box.left) / box.width) * LOGICAL_WIDTH,
      y: ((event.clientY - box.top) / box.height) * logicalHeight
    };
  }

  function changed() {
    hint.style.display = strokes.length ? 'none' : 'flex';

    if (typeof opts.onChange === 'function') opts.onChange(strokes.length === 0);
  }

  function onDown(event) {
    if (activePointer !== null || (event.pointerType === 'mouse' && event.button !== 0)) return;

    activePointer = event.pointerId;
    canvas.setPointerCapture(event.pointerId);
    current = [point(event)];
    strokes.push(current);
    redraw();
    changed();
    event.preventDefault();
  }

  function onMove(event) {
    if (event.pointerId !== activePointer || !current) return;

    const events = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [];
    (events.length ? events : [event]).forEach(function (e) {
      current.push(point(e));
    });
    redraw();
    event.preventDefault();
  }

  function onUp(event) {
    if (event.pointerId !== activePointer) return;

    activePointer = null;
    current = null;
  }

  canvas.addEventListener('pointerdown', onDown);
  canvas.addEventListener('pointermove', onMove);
  canvas.addEventListener('pointerup', onUp);
  canvas.addEventListener('pointercancel', onUp);
  canvas.addEventListener('contextmenu', function (event) {
    event.preventDefault();
  });

  let observer = null;
  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(resizeBackingStore);
    observer.observe(canvas);
  } else {
    window.addEventListener('resize', resizeBackingStore);
  }
  resizeBackingStore();

  /** The strokes' bounding box in logical units, with a little air around it. */
  function bounds() {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    strokes.forEach(function (points) {
      points.forEach(function (p) {
        minX = Math.min(minX, p.x);
        minY = Math.min(minY, p.y);
        maxX = Math.max(maxX, p.x);
        maxY = Math.max(maxY, p.y);
      });
    });

    return {
      x: Math.max(0, minX - PADDING),
      y: Math.max(0, minY - PADDING),
      w: Math.min(LOGICAL_WIDTH, maxX + PADDING) - Math.max(0, minX - PADDING),
      h: Math.min(logicalHeight, maxY + PADDING) - Math.max(0, minY - PADDING)
    };
  }

  function render(s) {
    const box = bounds();
    const out = document.createElement('canvas');
    out.width = Math.max(1, Math.ceil(box.w * s));
    out.height = Math.max(1, Math.ceil(box.h * s));
    paint(out.getContext('2d'), s, box.x, box.y);

    return out;
  }

  function toBlobAt(s) {
    return new Promise(function (resolve, reject) {
      render(s).toBlob(function (blob) {
        blob ? resolve(blob) : reject(fail('too_large'));
      }, 'image/png');
    });
  }

  const pad = {
    isEmpty: function () {
      return strokes.length === 0;
    },

    clear: function () {
      strokes.length = 0;
      current = null;
      activePointer = null;
      redraw();
      changed();
    },

    /** A transparent PNG of just the signature, shrunk until it fits under `maxBytes` (default 2 MB). */
    toBlob: async function (maxBytes) {
      if (strokes.length === 0) throw fail('empty');

      const limit = maxBytes || MAX_BYTES;
      let s = 2;

      for (let attempt = 0; attempt < 5; attempt++) {
        const blob = await toBlobAt(s);
        if (blob.size <= limit) return blob;
        s *= 0.6;
      }

      throw fail('too_large');
    },

    toFile: async function (name, maxBytes) {
      const blob = await pad.toBlob(maxBytes);

      return new File([blob], name || 'signature.png', { type: 'image/png' });
    },

    toDataURL: function () {
      if (strokes.length === 0) throw fail('empty');

      return render(2).toDataURL('image/png');
    },

    destroy: function () {
      canvas.removeEventListener('pointerdown', onDown);
      canvas.removeEventListener('pointermove', onMove);
      canvas.removeEventListener('pointerup', onUp);
      canvas.removeEventListener('pointercancel', onUp);
      if (observer) observer.disconnect();
      else window.removeEventListener('resize', resizeBackingStore);
      container.innerHTML = '';
    }
  };

  return pad;
};
