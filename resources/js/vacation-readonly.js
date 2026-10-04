/**
 * Read-only mode for an account that is "on vacation" (see
 * resources/views/_partials/vacation-banner.blade.php — it only renders for such an account, and its
 * presence is the switch for everything here).
 *
 * The server already refuses every write (EnsureTenant, 403); this makes that visible up front:
 *   - every form in the page content is locked (fields + submit button), so nobody fills one in and is
 *     told "no" at the end;
 *   - links/buttons that lead to a create/edit form or delete something are disabled with a tooltip;
 *   - as a last net, any non-GET axios request is stopped locally with the same message.
 * Tables and filters are rendered later by DataTables, so a MutationObserver re-applies this to whatever
 * appears. Export, search, filters, paging and the logout link keep working.
 */
const banner = document.getElementById('gcm-vacation-banner');
// The auditor only ever views (FRD V01.14: view / export on every page): its pages simply never show the
// controls that lead to a create / edit form — hidden, not greyed out, since they are not coming back.
const viewOnly = !banner && document.body.dataset.gcmViewOnly;

if (viewOnly) {
  const hideWrites = () =>
    document
      .querySelectorAll('.content-wrapper :is(a[href*="/add"], a[href*="/edit/"], a[href*="insert-into-project"], .add-new, .delete-category)')
      .forEach((el) => el.classList.add('d-none'));
  hideWrites();
  let hideQueued = false;
  new MutationObserver(() => {
    if (hideQueued) return;
    hideQueued = true;
    setTimeout(() => {
      hideQueued = false;
      hideWrites();
    }, 30);
  }).observe(document.body, { childList: true, subtree: true });
}

if (banner) {
  const message = banner.dataset.message;
  const hint = banner.dataset.disabledHint || message;

  // Links that open a create / edit form (sidebar included), and the "Add ..." / delete buttons.
  const WRITE_LINKS = 'a[href*="/add"], a[href*="/edit/"], a[href*="insert-into-project"]';
  const WRITE_BUTTONS = '.add-new, .delete-category';

  const lockForm = (form) => {
    // Not guarded by a "done" flag: widgets (Quill toolbars, Select2) add their own controls after the
    // form first appears, and those must be locked too. Only touches what is still enabled, so it settles.
    form.dataset.gcmVacationLocked = '1';
    form.classList.add('gcm-vacation-locked');
    form.querySelectorAll('input:not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled)').forEach((control) => {
      control.disabled = true;
    });
    // Rich-text editors (Quill) are contenteditable divs, not form controls.
    form.querySelectorAll('[contenteditable="true"]').forEach((el) => el.setAttribute('contenteditable', 'false'));
    form.title = hint;
  };

  const lockControl = (el) => {
    if (el.dataset.gcmVacationLocked) return;
    el.dataset.gcmVacationLocked = '1';
    el.classList.add('disabled');
    el.setAttribute('aria-disabled', 'true');
    el.setAttribute('tabindex', '-1');
    el.title = hint;
    if (el.tagName === 'BUTTON') el.disabled = true;
  };

  const apply = () => {
    document.querySelectorAll('.content-wrapper form').forEach(lockForm);
    document.querySelectorAll(`.content-wrapper :is(${WRITE_LINKS}), .menu-inner :is(${WRITE_LINKS}), .content-wrapper :is(${WRITE_BUTTONS})`).forEach(lockControl);
  };

  // A disabled <a> still navigates, so stop the click ourselves (capture: before the page-loader or any
  // DataTables handler sees it).
  document.addEventListener(
    'click',
    (event) => {
      const el = event.target.closest && event.target.closest('[data-gcm-vacation-locked]');
      if (el && !el.matches('form')) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    },
    true
  );

  let queued = false;
  const schedule = () => {
    if (queued) return;
    queued = true;
    // setTimeout, not requestAnimationFrame: rAF never fires in a hidden/background tab.
    setTimeout(() => {
      queued = false;
      apply();
    }, 30);
  };

  new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
  apply();

  if (window.axios) {
    window.axios.interceptors.request.use((config) => {
      const method = (config.method || 'get').toLowerCase();
      if (method === 'get' || method === 'head' || (config.url || '').includes('/auth/logout')) return config;

      return Promise.reject({ config, response: { status: 403, data: { message } } });
    });
  }
}
