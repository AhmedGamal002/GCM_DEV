/**
 * The trip form's cascading lists (part 7.4, FRD V01.14 §1.14) on top of the options API
 * (`GET /api/v1/trips/options/*`, part 7.2). One engine for the staff form (add + edit) and, later, the
 * driver's own form (7.6): the page gives it the <select>s, it fills them from the server, keeps them in step
 * and clears whatever a change makes invalid. The server re-checks every choice on save (422 on the field's
 * name) — this is for the user's convenience, never the guard.
 *
 *   const lists = gcmTripFormLists({
 *     staff: true,                       // false = the driver's own form (no driver / PO / approver lists)
 *     tripId: null,                      // the trip being edited: its own containers stay free for it
 *     selects: { driver_id: el, vehicle_id: el, company_id: el, project_id: el, purchase_order_id: el,
 *                main_service_id: el, sub_service_id: el, going_asset_id: el, returning_asset_id: el,
 *                intermediate_facility_id: el, client_approver_id: el, gcm_approver_id: el },
 *     onCapacity: (data|null) => {},     // {capacity, source, default_quantity:{ton,cbm}, converter}
 *     onPurchaseOrders: (response) => {},// the PO list with each line's costs / status and meta
 *     onChange: (field, item|null) => {},
 *     onError: (field, error) => {}
 *   });
 *   lists.set('project_id', { id, text }, { silent: true })   // prefill (edit): no clearing, no defaults
 *   lists.setExtra('trip_at', '2026-11-18T10:30')              // dates the PO statuses
 *   lists.setExtra('requires_purchase_order', false)           // "without PO": no PO list, services of the vehicle
 *   lists.get('vehicle_id'), lists.item('vehicle_id'), lists.destroy()
 *
 * Which choice clears which (the same pairs the server re-checks — TripChoiceCheck):
 *   driver → vehicle (and picks the driver's default vehicle)      company → project
 *   project → PO, main service, returning container, client approver
 *   vehicle → main service, both containers, sub-service           PO → main service
 *   main service → sub-service (and re-reads the PO list's costs and the capacity)
 *   going / returning container → sub-service                      sub-service → facility
 * On a NEW trip the defaults are picked for the user when the field is empty: the driver's default vehicle, the
 * PO the trip would use, the project's default client approver and the signed-in user as GCM approver.
 *
 * Names come back raw from the API; the option text is set as text, never as HTML.
 */
'use strict';

const BASE = '/api/v1/trips/options/';
const PAGE = 20;

function t(key, fallback) {
  const i18n = window.gcmI18n || {};

  return i18n[key] || fallback;
}

const join = (...parts) => parts.filter((p) => p !== null && p !== undefined && p !== '').join(' · ');

/** field → how its list is asked for, shown and kept in step. `needs` = what must be chosen before it opens. */
const LISTS = {
  driver_id: {
    endpoint: 'drivers',
    staff: true,
    needs: () => [],
    params: () => ({}),
    clears: ['vehicle_id'],
    label: (d) => join(d.name, d.code, d.entity_name)
  },
  vehicle_id: {
    endpoint: 'vehicles',
    needs: (v, o) => (o.staff ? ['driver_id'] : []),
    params: (v, o) => (o.staff ? { driver_id: v.driver_id } : {}),
    clears: ['main_service_id', 'going_asset_id', 'returning_asset_id', 'sub_service_id'],
    refresh: ['capacity'],
    label: (x) =>
      join(x.plate, x.category && x.category.name, x.entity_name, x.is_default ? '(' + t('trip_default', 'default') + ')' : '')
  },
  company_id: {
    endpoint: 'companies',
    needs: () => [],
    params: () => ({}),
    clears: ['project_id'],
    label: (c) => join(c.name, c.code)
  },
  project_id: {
    endpoint: 'projects',
    needs: () => ['company_id'],
    params: (v) => ({ company_id: v.company_id }),
    clears: ['purchase_order_id', 'main_service_id', 'returning_asset_id', 'client_approver_id'],
    refresh: ['purchase_order_id'],
    label: (p) => join(p.name, p.code)
  },
  purchase_order_id: {
    endpoint: 'purchase-orders',
    staff: true,
    paged: false,
    needs: (v) => (v.requires_purchase_order === false ? ['never'] : ['project_id']),
    params: (v) => ({ project_id: v.project_id, main_service_id: v.main_service_id, trip_at: v.trip_at }),
    clears: ['main_service_id'],
    label: (po) => join(po.number, po.timeline_label, po.is_default ? '(' + t('trip_default', 'default') + ')' : '')
  },
  main_service_id: {
    endpoint: 'main-services',
    needs: () => ['vehicle_id', 'project_id'],
    params: (v, o) => ({
      vehicle_id: v.vehicle_id,
      project_id: v.project_id,
      ...(o.staff
        ? {
            requires_purchase_order: v.requires_purchase_order === false ? 0 : 1,
            purchase_order_id: v.requires_purchase_order === false ? undefined : v.purchase_order_id
          }
        : {})
    }),
    clears: ['sub_service_id'],
    refresh: ['purchase_order_id', 'capacity'],
    label: (s) => s.name
  },
  sub_service_id: {
    endpoint: 'sub-services',
    needs: () => ['main_service_id'],
    params: (v) => ({
      main_service_id: v.main_service_id,
      vehicle_id: v.vehicle_id,
      going_asset_id: v.going_asset_id,
      returning_asset_id: v.returning_asset_id
    }),
    clears: ['intermediate_facility_id'],
    label: (s) => join(s.name, s.skip_color_label)
  },
  going_asset_id: {
    endpoint: 'going-assets',
    needs: () => ['vehicle_id'],
    params: (v, o) => ({ vehicle_id: v.vehicle_id, trip_id: o.tripId }),
    clears: ['sub_service_id'],
    refresh: ['returning_asset_id'],
    label: (a) => join(a.name, a.capacity && a.capacity.name, a.entity_name)
  },
  returning_asset_id: {
    endpoint: 'returning-assets',
    needs: () => ['project_id'],
    params: (v, o) => ({
      project_id: v.project_id,
      vehicle_id: v.vehicle_id,
      going_asset_id: v.going_asset_id,
      trip_id: o.tripId
    }),
    clears: ['sub_service_id'],
    refresh: ['capacity'],
    label: (a) => join(a.name, a.capacity && a.capacity.name, a.entity_name)
  },
  intermediate_facility_id: {
    endpoint: 'facilities',
    needs: () => ['sub_service_id'],
    params: (v) => ({ sub_service_id: v.sub_service_id }),
    clears: [],
    label: (f) => f.name
  },
  client_approver_id: {
    endpoint: 'client-approvers',
    staff: true,
    needs: () => ['project_id'],
    params: (v) => ({ project_id: v.project_id }),
    clears: [],
    label: (u) => u.name
  },
  gcm_approver_id: {
    endpoint: 'gcm-approvers',
    staff: true,
    needs: () => [],
    params: () => ({}),
    clears: [],
    label: (u) => u.name
  }
};

/** Request params without the empty ones. */
function clean(params) {
  const out = {};

  Object.keys(params).forEach(function (key) {
    const value = params[key];
    if (value !== undefined && value !== null && value !== '') out[key] = value;
  });

  return out;
}

function defaultLoad(name, params) {
  return window.axios.get(BASE + name, { params: clean(params) }).then((response) => response.data);
}

window.gcmTripFormLists = function (options) {
  const o = Object.assign({ staff: true, tripId: null, selects: {}, load: defaultLoad }, options || {});
  const $ = window.$;
  const values = { requires_purchase_order: true, trip_at: null };
  const items = {};
  const tokens = {};
  let destroyed = false;

  const fields = Object.keys(LISTS).filter((f) => o.selects[f] && (o.staff || !LISTS[f].staff));

  const state = () => Object.assign({}, values, { trip_id: o.tripId });
  const ready = (field) => LISTS[field].needs(state(), o).every((k) => values[k] !== undefined && values[k] !== null && values[k] !== '');
  const fail = (field, error) => (typeof o.onError === 'function' ? o.onError(field, error) : console.error(field, error));

  // ---------------------------------------------------------------- widgets

  function wrap($el) {
    if (!$el.parent().hasClass('gcm-select2-wrap')) {
      $el.wrap('<div class="position-relative gcm-select2-wrap"></div>');
    }
  }

  /** (Re)builds one field's widget: closed while what it depends on is missing, otherwise a live-search list. */
  function render(field) {
    const select = o.selects[field];
    if (!select || !$ || !$.fn || !$.fn.select2) return;

    const def = LISTS[field];
    const $el = $(select);
    if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
    wrap($el);

    const base = { dropdownParent: $el.parent(), width: '100%', allowClear: true, placeholder: select.dataset.placeholder || ' ' };
    select.disabled = !ready(field);

    if (def.paged === false) {
      $el.select2(base);

      return;
    }

    $el.select2(
      Object.assign(base, {
        ajax: {
          delay: 250,
          cache: false,
          transport: function (params, success, failure) {
            o.load(def.endpoint, Object.assign({}, def.params(state(), o), { search: params.data.search, page: params.data.page, per_page: PAGE }))
              .then(success)
              .catch(function (error) {
                failure(error);
                fail(field, error);
              });
          },
          data: (params) => ({ search: params.term || '', page: params.page || 1 }),
          processResults: (response, params) => ({
            results: response.data.map((item) => ({ id: item.id, text: def.label(item), item })),
            pagination: { more: response.meta.current_page < response.meta.last_page }
          })
        }
      })
    );

    $el.off('select2:select.gcmtrip').on('select2:select.gcmtrip', (e) => changed(field, e.params.data.item || null));
  }

  function setOption(field, id, text) {
    const select = o.selects[field];
    select.innerHTML = '';

    if (id !== null) {
      const option = document.createElement('option');
      option.value = String(id);
      option.textContent = text; // text, never HTML
      option.selected = true;
      select.appendChild(option);
    }
  }

  // ---------------------------------------------------------------- cascade

  function store(field, id, item) {
    values[field] = id;
    items[field] = item || null;
  }

  /** Empties a field and, because it changed, whatever depends on it. `notify` tells the page. */
  function clearField(field, notify) {
    if (!o.selects[field] || values[field] === undefined || values[field] === null) {
      return;
    }

    store(field, null, null);
    setOption(field, null);
    render(field);
    afterChange(field, false);
    if (notify && typeof o.onChange === 'function') o.onChange(field, null);
  }

  /** What a change of `field` does to the others. `silent` = a prefill: nothing is cleared and no default is picked. */
  function afterChange(field, silent) {
    if (destroyed) return;

    const def = LISTS[field];

    if (!silent) {
      def.clears.forEach((child) => clearField(child, true));
    }

    // every list that depends on this one opens / closes and starts afresh
    fields.forEach((f) => {
      if (f !== field && LISTS[f].needs(state(), o).length) render(f);
    });

    (def.refresh || []).forEach((r) => (r === 'capacity' ? loadCapacity() : refreshPurchaseOrders(silent)));

    if (!silent) applyDefaults(field);
  }

  function changed(field, item) {
    const value = o.selects[field].value;
    store(field, value === '' ? null : Number(value), item);
    afterChange(field, false);
    if (typeof o.onChange === 'function') o.onChange(field, items[field]);
  }

  // ---------------------------------------------------------------- the lists that are not plain searches

  function loadCapacity() {
    if (typeof o.onCapacity !== 'function') return;

    if (!values.vehicle_id) {
      o.onCapacity(null);

      return;
    }

    const token = (tokens.capacity = (tokens.capacity || 0) + 1);
    o.load('capacity', { vehicle_id: values.vehicle_id, main_service_id: values.main_service_id, returning_asset_id: values.returning_asset_id })
      .then((response) => token === tokens.capacity && !destroyed && o.onCapacity(response.data))
      .catch((error) => fail('capacity', error));
  }

  /** The PO list is not paged: it is read whole, and re-read when the service or the date changes (costs, statuses). */
  function refreshPurchaseOrders(silent) {
    const field = 'purchase_order_id';
    if (!o.staff || !o.selects[field] || !ready(field)) {
      if (o.selects[field]) setPurchaseOrderOptions([], null);

      return Promise.resolve();
    }

    const token = (tokens[field] = (tokens[field] || 0) + 1);

    return o
      .load('purchase-orders', LISTS[field].params(state(), o))
      .then((response) => {
        if (token !== tokens[field] || destroyed) return;

        const keep = values[field];
        const stillThere = response.data.some((po) => po.id === keep);
        setPurchaseOrderOptions(response.data, stillThere ? keep : null);

        if (!stillThere && keep !== null && keep !== undefined) store(field, null, null);
        if (stillThere) items[field] = response.data.find((po) => po.id === keep);

        if (!silent && values[field] == null && o.tripId == null && response.meta.default_purchase_order_id && values.main_service_id) {
          const po = response.data.find((x) => x.id === response.meta.default_purchase_order_id);
          if (po) {
            store(field, po.id, po);
            setPurchaseOrderOptions(response.data, po.id);
          }
        }

        if (typeof o.onPurchaseOrders === 'function') o.onPurchaseOrders(response, values[field]);
      })
      .catch((error) => fail(field, error));
  }

  function setPurchaseOrderOptions(list, selected) {
    const select = o.selects.purchase_order_id;
    select.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = '';
    select.appendChild(blank);

    list.forEach(function (po) {
      const option = document.createElement('option');
      option.value = String(po.id);
      option.textContent = LISTS.purchase_order_id.label(po);
      option.selected = po.id === selected;
      select.appendChild(option);
    });

    render('purchase_order_id');
    $(select)
      .off('change.gcmtrip')
      .on('change.gcmtrip', () => {
        const id = select.value === '' ? null : Number(select.value);
        changed('purchase_order_id', list.find((po) => po.id === id) || null);
      });
  }

  /** Defaults for a NEW trip, only into an empty field. */
  function applyDefaults(field) {
    if (o.tripId !== null && o.tripId !== undefined) return;

    if (field === 'driver_id' && values.driver_id && o.selects.vehicle_id) {
      pickDefault('vehicle_id', 'vehicles', { driver_id: values.driver_id, per_page: 100 }, (x) => x.is_default === true);
    }

    if (field === 'project_id' && values.project_id && o.selects.client_approver_id && o.staff) {
      pickDefault('client_approver_id', 'client-approvers', { project_id: values.project_id, per_page: 100 }, null, (meta) => meta.default_id);
    }
  }

  function pickDefault(field, endpoint, params, test, metaId) {
    if (values[field] != null) return;

    const token = (tokens['d' + field] = (tokens['d' + field] || 0) + 1);
    o.load(endpoint, params)
      .then((response) => {
        if (token !== tokens['d' + field] || destroyed || values[field] != null) return;

        // the server names the default (meta) or each row says so (is_default); neither = nothing to pick
        const id = metaId ? metaId(response.meta) : null;
        if (metaId && !id) return;

        const item = response.data.find((x) => (metaId ? x.id === id : test(x)));
        if (!item) return;

        set(field, { id: item.id, text: LISTS[field].label(item), item }, {});
      })
      .catch((error) => fail(field, error));
  }

  // ---------------------------------------------------------------- public

  function set(field, choice, opts) {
    if (!o.selects[field]) return;

    const silent = !!(opts && opts.silent);
    if (choice === null || choice === undefined) {
      clearField(field, !silent);

      return;
    }

    const item = choice.item || null;
    store(field, Number(choice.id), item);
    setOption(field, Number(choice.id), choice.text);
    render(field);
    afterChange(field, silent);
    if (typeof o.onChange === 'function') o.onChange(field, item);
  }

  fields.forEach((field) => {
    values[field] = null;
    items[field] = null;
    render(field);

    // a cleared widget (the x) is a change too
    $(o.selects[field])
      .off('select2:clear.gcmtrip')
      .on('select2:clear.gcmtrip', () => changed(field, null));
  });

  // the signed-in user is the default GCM approver of a NEW trip
  if (o.staff && o.selects.gcm_approver_id && (o.tripId === null || o.tripId === undefined)) {
    pickDefault('gcm_approver_id', 'gcm-approvers', { per_page: 100 }, null, (meta) => meta.default_id);
  }

  return {
    get: (field) => (values[field] === undefined ? null : values[field]),
    item: (field) => items[field] || null,
    set,
    clear: (field) => set(field, null),
    setExtra(key, value) {
      values[key] = value;

      if (key === 'requires_purchase_order') {
        // "without a PO" widens the services (any of the vehicle's), so the chosen one stays valid; back to "needs a PO"
        // the chosen service may not be covered, so it is cleared.
        if (value === false) clearField('purchase_order_id', true);
        else clearField('main_service_id', true);
        render('main_service_id');
        refreshPurchaseOrders(false);
      }

      if (key === 'trip_at') refreshPurchaseOrders(true);
    },
    refresh: (field) => render(field),
    destroy() {
      destroyed = true;
    }
  };
};

/** ton ↔ m³ with the factor the API sends (`converter`), never one written here (ج10). */
window.gcmTripQuantity = {
  toCbm: (ton, converter) => round(Number(ton) / converter.tonnes_per_cubic_meter, converter.decimals),
  toTon: (cbm, converter) => round(Number(cbm) * converter.tonnes_per_cubic_meter, converter.decimals)
};

function round(n, decimals) {
  const f = Math.pow(10, decimals);

  return Math.round(n * f) / f;
}
