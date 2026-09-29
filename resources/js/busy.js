/**
 * "Something slow is happening" feedback, shared by every page.
 *
 *  - gcmBusy: a full-screen overlay with a spinner, an optional upload
 *    progress bar, and a failure state. Slow multipart saves (a driver with
 *    four scanned documents) and PDF exports otherwise look exactly like a
 *    frozen page, and an impatient second click on Save can submit twice.
 *  - gcmDownload(url): fetches an export as a blob so the page can show the
 *    overlay while the server works, then hands the file to the browser.
 *    (A plain window.location.assign() on a slow export just leaves the tab
 *    spinning with no explanation.)
 *  - gcmFileGuard(form, limits): checks selected files against the server's
 *    size limits BEFORE uploading, so a too-big file fails in a second
 *    instead of after the whole upload.
 *
 * Texts come from window.gcmTexts (injected by layouts/sections/scripts.blade.php,
 * translated), so nothing here is hardcoded in one language.
 */
const T = () => window.gcmTexts || {};

let root;
let els = {};

function build() {
  if (root) return;

  root = document.createElement('div');
  root.id = 'gcm-busy';
  root.setAttribute('role', 'alert');
  root.setAttribute('aria-live', 'assertive');
  root.style.cssText =
    'position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;background:rgba(20,22,35,.6)';
  root.innerHTML =
    '<div class="card shadow-lg" style="min-width:300px;max-width:92vw">' +
    '<div class="card-body text-center py-6 px-6">' +
    '<div class="spinner-border text-primary mb-4" role="status" data-spinner></div>' +
    '<p class="mb-2 fw-medium" data-msg></p>' +
    '<div class="progress mb-2 d-none" style="height:8px"><div class="progress-bar" role="progressbar" data-bar style="width:0%"></div></div>' +
    '<small class="text-muted d-block" data-hint></small>' +
    '<button type="button" class="btn btn-primary mt-4 d-none" data-close></button>' +
    '</div></div>';
  document.body.appendChild(root);

  els = {
    spinner: root.querySelector('[data-spinner]'),
    msg: root.querySelector('[data-msg]'),
    progress: root.querySelector('.progress'),
    bar: root.querySelector('[data-bar]'),
    hint: root.querySelector('[data-hint]'),
    close: root.querySelector('[data-close]'),
  };
  els.close.addEventListener('click', () => gcmBusy.stop());
}

export const gcmBusy = {
  start({ message, hint = '', progress = false } = {}) {
    build();
    els.spinner.classList.remove('d-none');
    els.msg.textContent = message || (progress ? (T().uploading || '').replace(':percent', 0) : T().working || '');
    els.msg.classList.remove('text-danger');
    els.hint.textContent = hint;
    els.close.classList.add('d-none');
    els.progress.classList.toggle('d-none', !progress);
    els.bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    els.bar.style.width = '0%';
    root.style.display = 'flex';
  },

  /** Pass to axios as `onUploadProgress`. */
  onUploadProgress(event) {
    if (!event.total) return;
    const pct = Math.min(100, Math.round((event.loaded * 100) / event.total));
    els.bar.style.width = pct + '%';

    if (pct >= 100) {
      // Upload done — the server is still validating/storing. Show that as
      // its own state instead of a bar stuck at 100%.
      els.msg.textContent = T().processing || '';
      els.bar.classList.add('progress-bar-striped', 'progress-bar-animated');
    } else {
      els.msg.textContent = (T().uploading || '').replace(':percent', pct);
    }
  },

  fail(message) {
    build();
    els.spinner.classList.add('d-none');
    els.progress.classList.add('d-none');
    els.msg.textContent = message;
    els.msg.classList.add('text-danger');
    els.hint.textContent = '';
    els.close.textContent = T().close || 'OK';
    els.close.classList.remove('d-none');
    root.style.display = 'flex';
  },

  stop() {
    if (root) root.style.display = 'none';
  },
};

/**
 * Download an export (or any file endpoint) with the busy overlay up while
 * the server generates it.
 */
export async function gcmDownload(url, fallbackName = 'export') {
  gcmBusy.start({ message: T().preparing, hint: T().preparingHint });

  try {
    const res = await window.axios.get(url, { responseType: 'blob', timeout: 0 });

    const disposition = res.headers['content-disposition'] || '';
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const name = match ? decodeURIComponent(match[1]) : fallbackName;

    const link = document.createElement('a');
    link.href = URL.createObjectURL(res.data);
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 10000);

    gcmBusy.stop();
  } catch (error) {
    let message = T().exportFailed;

    // A failed blob request still carries the JSON error body, as a Blob.
    const data = error.response && error.response.data;
    if (data instanceof Blob) {
      try {
        message = JSON.parse(await data.text()).message || message;
      } catch (e) {
        // not JSON — keep the generic message
      }
    }

    gcmBusy.fail(message);
  }
}

/**
 * @param {HTMLFormElement} form
 * @param {{default: number, [name: string]: number}} limitsKb per-input limits in KB,
 *        keyed by the input's `name` or `id` (falling back to `default`) — mirror the server rules.
 * @returns {string|null} a translated message for the first oversized file, or null
 */
export function gcmFileGuard(form, limitsKb) {
  for (const input of form.querySelectorAll('input[type="file"]')) {
    const file = input.files && input.files[0];
    if (!file) continue;

    // Some forms name their file inputs, others only give them an id.
    const maxKb = limitsKb[input.name] ?? limitsKb[input.id] ?? limitsKb.default;
    if (file.size > maxKb * 1024) {
      return (T().fileTooLarge || '')
        .replace(':file', file.name)
        .replace(':size', (file.size / 1048576).toFixed(1))
        .replace(':max', (maxKb / 1024).toFixed(0));
    }
  }

  return null;
}

window.gcmBusy = gcmBusy;
window.gcmDownload = gcmDownload;
window.gcmFileGuard = gcmFileGuard;
