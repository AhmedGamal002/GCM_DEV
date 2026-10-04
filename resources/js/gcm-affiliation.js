/**
 * The "affiliation" radio shared by the driver, vehicle and asset forms (FRD
 * V01.14 §1.3 / §1.5 / §1.7): belongs to GCM, or to a contractor — and
 * choosing a contractor reveals a searchable list of contractors to pick one
 * from. The markup is the `_partials.affiliation-field` Blade partial.
 *
 *   const affiliation = window.gcmAffiliation(form);
 *   affiliation.set('contractor', { id, name, code });   // edit pages: prefill
 *   affiliation.lock();                                   // read-only (asset edit)
 *
 * Only ACTIVE contractors are offered (a deactivated one is "no longer
 * offered for new work"), plus the record's current contractor so editing an
 * old record never loses it. Needs select2 loaded by the page (like every
 * form select — see gcm-select2.js).
 */
'use strict';

function option(value, label) {
  const opt = document.createElement('option');
  opt.value = value;
  opt.textContent = label; // user input — textContent, never innerHTML
  return opt;
}

const labelOf = (c) => (c.code ? `${c.name} (${c.code})` : c.name);

window.gcmAffiliation = function (root) {
  const radios = Array.from(root.querySelectorAll('input[name="affiliation"]'));
  const wrap = root.querySelector('[data-affiliation-contractor]');
  const select = root.querySelector('#contractor_id');
  if (!radios.length || !wrap || !select) return null;

  let loaded = null; // Promise of the contractor list, fetched once
  let current = null; // the record's own contractor, kept selectable
  let pendingValue = null; // an id chosen before the list arrived

  const chosen = () => {
    const r = radios.find((x) => x.checked);
    return r ? r.value : null;
  };

  function render() {
    const keep = select.value || (pendingValue != null ? String(pendingValue) : '');
    select.innerHTML = '<option value=""></option>';

    const seen = new Set();
    (loaded.list || []).concat(current ? [current] : []).forEach((c) => {
      if (seen.has(String(c.id))) return;
      seen.add(String(c.id));
      select.appendChild(option(c.id, labelOf(c)));
    });

    select.value = keep;
    window.initGcmSelects(root);
    window.refreshGcmSelect(select);
  }

  function load() {
    if (!loaded) {
      loaded = { list: [] };
      loaded.promise = window
        .gcmFetchAll('/api/v1/contractors', { operational_status: 'active', sort_by: 'name' })
        .then((rows) => {
          loaded.list = rows;
        })
        .catch(() => {});
    }
    return loaded.promise;
  }

  function sync() {
    const isContractor = chosen() === 'contractor';
    wrap.classList.toggle('d-none', !isContractor);
    // A disabled select is neither validated nor submitted — a GCM record carries no contractor.
    select.disabled = !isContractor;
    if (isContractor) {
      load().then(render);
    } else {
      window.refreshGcmSelect(select);
    }
  }

  radios.forEach((r) => r.addEventListener('change', sync));
  window.initGcmSelects(root);
  sync();

  return {
    /** Prefill (edit pages): `contractor` is the {id, name, code} the API returns. */
    set(affiliation, contractor) {
      const radio = radios.find((r) => r.value === affiliation);
      if (radio) radio.checked = true;
      current = contractor || null;
      pendingValue = contractor ? contractor.id : null;
      sync();
    },
    /** Current choice, for forms that build their own FormData. */
    get() {
      const isContractor = chosen() === 'contractor';
      return { affiliation: chosen(), contractor_id: isContractor ? select.value : '' };
    },
    /** Read-only display of a locked choice (asset edit). */
    lock() {
      radios.forEach((r) => (r.disabled = true));
      select.disabled = true;
      window.refreshGcmSelect(select);
    }
  };
};
