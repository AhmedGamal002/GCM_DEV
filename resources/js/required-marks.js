/**
 * Marks every mandatory field of every form with a red "*" on its label, and puts a one-line legend
 * ("Fields marked with * are required.") at the top of the form — so what is required is visible before
 * anyone submits.
 *
 * What counts as mandatory is what the form already declares: a control with the `required` attribute, a
 * radio group where one radio has `required`, or a container carrying `data-required` (checkbox lists
 * built by JS). Adding `required` to a new field is all a developer has to do — the mark follows.
 * Forms render partly later (edit pages show after their data loads, modals, JS-built groups), so this
 * re-runs on DOM changes. Login / password-reset pages are left alone (they are not in the app layout's
 * content area).
 */
const FORMS = '.content-wrapper form, .modal form';

const groupLabel = (el) => {
  const col = el.closest('[class*="col-"]') || el.closest('.mb-4, .mb-6') || el.parentElement;
  return col ? col.querySelector('label.form-label, label.d-block') : null;
};

const labelOf = (el) => {
  if (el.matches('[data-required]') || (el.matches('input[type="radio"], input[type="checkbox"]') && el.name)) return groupLabel(el);
  if (el.id) {
    const byFor = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
    if (byFor) return byFor;
  }
  return groupLabel(el);
};

const mark = (label) => {
  if (!label || label.dataset.gcmRequired) return;
  label.dataset.gcmRequired = '1';
  const star = document.createElement('span');
  star.className = 'text-danger ms-1';
  star.setAttribute('aria-hidden', 'true');
  star.textContent = '*';
  label.appendChild(star);
};

const apply = () => {
  document.querySelectorAll(FORMS).forEach((form) => {
    let any = false;

    form.querySelectorAll('[required], [data-required]').forEach((el) => {
      // A checkbox that only confirms something (status-change "I confirm") is not a form field.
      if (el.type === 'checkbox' && !el.name) return;
      if (el.closest('[data-no-required-mark]')) return;
      mark(labelOf(el));
      any = true;
    });

    if (any && !form.querySelector(':scope > .gcm-required-hint')) {
      const text = (window.gcmI18n || {}).required_hint;
      if (text) {
        const hint = document.createElement('p');
        hint.className = 'gcm-required-hint text-muted small mb-4';
        hint.textContent = text;
        form.insertBefore(hint, form.firstChild);
      }
    }
  });
};

let queued = false;
const schedule = () => {
  if (queued) return;
  queued = true;
  // setTimeout, not requestAnimationFrame: rAF never fires in a hidden/background tab.
  setTimeout(() => {
    queued = false;
    apply();
  }, 50);
};

document.addEventListener('DOMContentLoaded', () => {
  apply();
  new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
});
