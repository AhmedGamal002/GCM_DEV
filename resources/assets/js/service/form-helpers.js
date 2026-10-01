/**
 * Shared bits of the main-service and sub-service forms (FRD §1.9): inline
 * per-field errors (client + the server's 422 map), checkbox groups built
 * from reference data, and the "last updated by" stamp.
 *
 * A field's error slot is the `[data-feedback]` inside its column. Checkbox
 * groups have no single input to hang an error on, so their column carries
 * `data-error-for="<field>"` — server keys like `capacity_ids.0` map to the
 * group by their base name.
 *
 * Lives in a subdirectory so the `resources/assets/js/*.js` Vite glob
 * doesn't pick it up as its own entry.
 */
'use strict';

export function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value == null ? '' : String(value);
  return div.innerHTML;
}

export function createFormErrors(form, errorBox, t) {
  function slotFor(el) {
    const wrapper = el.closest('[data-error-for]') || el.closest('[class*="col-"]') || el.parentElement;
    return wrapper ? wrapper.querySelector('[data-feedback]') : null;
  }

  function targetFor(field) {
    return form.querySelector(`[data-error-for="${field}"]`) || form.querySelector(`[name="${field}"]`);
  }

  function show(el, msg) {
    if (el.matches('[data-error-for]')) {
      el.querySelectorAll('input').forEach((i) => i.classList.add('is-invalid'));
    } else if (el.type === 'radio' && el.name) {
      form.querySelectorAll(`input[name="${el.name}"]`).forEach((r) => r.classList.add('is-invalid'));
    } else {
      el.classList.add('is-invalid');
    }
    const fb = slotFor(el);
    if (fb) {
      fb.textContent = msg;
      fb.classList.remove('d-none');
    }
  }

  function clear(el) {
    const group = el.closest('[data-error-for]');
    const scope = group || el;
    if (group) {
      group.querySelectorAll('.is-invalid').forEach((i) => i.classList.remove('is-invalid'));
    } else if (el.type === 'radio' && el.name) {
      form.querySelectorAll(`input[name="${el.name}"]`).forEach((r) => r.classList.remove('is-invalid'));
    } else {
      el.classList.remove('is-invalid');
    }
    const fb = slotFor(scope);
    if (fb) {
      fb.textContent = '';
      fb.classList.add('d-none');
    }
  }

  function clearAll() {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('[data-feedback]').forEach((fb) => {
      fb.textContent = '';
      fb.classList.add('d-none');
    });
    errorBox.textContent = '';
    errorBox.classList.add('d-none');
  }

  function showMessage(msg) {
    errorBox.textContent = msg || t.generic_error || 'Something went wrong.';
    errorBox.classList.remove('d-none');
    window.scrollTo(0, 0);
  }

  function applyServer(errors) {
    let first = null;
    const unmapped = [];
    Object.entries(errors).forEach(([key, messages]) => {
      const el = targetFor(key.split('.')[0]);
      if (el) {
        show(el, messages[0]);
        if (!first) first = el;
      } else {
        unmapped.push(messages[0]);
      }
    });
    if (unmapped.length) {
      errorBox.textContent = unmapped.join(' ');
      errorBox.classList.remove('d-none');
    }
    if (first && first.scrollIntoView) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    else window.scrollTo(0, 0);
  }

  function handleFailure(error) {
    const res = error.response;
    if (res && res.status === 422 && res.data && res.data.errors) {
      applyServer(res.data.errors);
    } else {
      showMessage(res && res.data && res.data.message);
    }
  }

  const clearOnInteract = (e) => {
    if (e.target.matches('input, select, textarea')) clear(e.target);
  };
  form.addEventListener('input', clearOnInteract);
  form.addEventListener('change', clearOnInteract);

  return { show, clear, clearAll, targetFor, showMessage, handleFailure };
}

/**
 * Fills `box` with one checkbox per item. `items`: [{value, label, hint?}];
 * `checked`: values to tick. Labels are user input — escaped.
 */
export function renderCheckboxes(box, name, items, checked, emptyText) {
  const chosen = new Set((checked || []).map(String));
  if (!items.length) {
    box.innerHTML = '<div class="col-12 text-muted small">' + escapeHtml(emptyText || '') + '</div>';
    return;
  }
  box.innerHTML = items
    .map((item) => {
      const id = `${name}-${item.value}`;
      return (
        '<div class="col-md-4 col-sm-6"><div class="form-check">' +
        `<input class="form-check-input" type="checkbox" name="${name}" id="${id}" value="${escapeHtml(item.value)}"` +
        (chosen.has(String(item.value)) ? ' checked' : '') +
        '>' +
        `<label class="form-check-label" for="${id}">${escapeHtml(item.label)}` +
        (item.hint ? ` <small class="text-muted">${escapeHtml(item.hint)}</small>` : '') +
        '</label></div></div>'
      );
    })
    .join('');
}

export function checkedValues(form, name) {
  return Array.from(form.querySelectorAll(`input[name="${name}"]:checked`)).map((i) => i.value);
}

export function setLastUpdated(elId, entity, t) {
  const stamp = document.getElementById(elId);
  if (stamp && entity.updated_by_name && t.last_updated_by) {
    const at = entity.updated_at ? new Date(entity.updated_at).toLocaleString() : '';
    stamp.textContent = t.last_updated_by.replace(':name', entity.updated_by_name).replace(':at', at);
  }
}
