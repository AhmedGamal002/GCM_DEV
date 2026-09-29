/**
 * Live-search selects — general rule for every add/edit form: any real
 * dropdown (`<select class="select2">`) gets a searchable "type to
 * filter" popup instead of a plain native list, same widget as the demo
 * at /forms/selects ("Select2" → "Default"). A page that uses it loads
 * select2's own vendor CSS/JS in its vendor-style/vendor-script section
 * (copy the pattern from any add/edit view that already has one) and
 * calls the two helpers below from its own page script — everything else
 * (positioning, RTL, dark mode) select2's own vendor CSS already handles,
 * the same as it does on the demo page.
 *
 * window.initGcmSelects(root): wires every `.select2` under `root`
 * (default: the whole page) that isn't wired yet. Call it once for a
 * select whose <option>s are already in the Blade markup at load time
 * (e.g. a fixed set of choices), typically right after the form element
 * is grabbed.
 *
 * window.refreshGcmSelect(select): re-wires ONE select after its
 * <option>s were just replaced by JS — reference data fetched async, or
 * one field's choices depending on another (a vehicle-category picker
 * whose options depend on a capacity type, say). Select2 snapshots the
 * option list at init time, so appending new <option> elements to an
 * already-wired select does NOT make them searchable — this always
 * destroys the previous instance first, then builds a fresh one. Call it
 * at the END of whatever function rebuilds that select's <option>s,
 * after any `select.value = x` re-selection the same function does —
 * Select2 reads whichever <option> the DOM already marks selected.
 *
 * Selecting a value with plain `select.value = x` on a select that is
 * ALREADY wired, WITHOUT rebuilding its options (no refreshGcmSelect
 * call), needs one extra step: Select2 only notices through the native
 * 'change' event, so follow it with `$(select).trigger('change')`.
 */
'use strict';

function wire($el) {
  if (!$el.parent().hasClass('gcm-select2-wrap')) {
    $el.wrap('<div class="position-relative gcm-select2-wrap"></div>');
  }
  $el.select2({
    dropdownParent: $el.parent(),
    width: '100%',
    placeholder: $el.data('placeholder') || $el.find('option[value=""]').first().text() || null
  });
}

function initGcmSelects(root) {
  if (!window.$ || !window.$.fn || !window.$.fn.select2) return;

  window
    .$(root || document)
    .find('select.select2')
    .not('.select2-hidden-accessible')
    .each(function () {
      wire(window.$(this));
    });
}

function refreshGcmSelect(select) {
  if (!window.$ || !window.$.fn || !window.$.fn.select2 || !select) return;

  const $el = select.jquery ? select : window.$(select);
  if ($el.hasClass('select2-hidden-accessible')) {
    $el.select2('destroy');
  }
  wire($el);
}

window.initGcmSelects = initGcmSelects;
window.refreshGcmSelect = refreshGcmSelect;
