/**
 * The "Trip documents" card of a trip's details page (part 8.1): the generated delivery note and waste manifest
 * the viewer may open, and — for GCM staff — the combined PDF. Shared by the staff, driver, contractor and client pages.
 *
 * `trip.documents` is what GET /trips/{id} returns for THIS role — a key that is not there is not shown
 * (a supplying trip has no waste manifest; the driver gets the delivery note only), `export_url` is null for
 * everyone but staff and before the trip is completed. A document's `number` is reserved when the trip completes
 * (a trip completed before that gets it the first time it is opened): with no number the name is shown alone.
 * Nothing here decides who sees what — the server did.
 */
'use strict';

const KINDS = ['delivery_note', 'waste_manifest'];

/**
 * @param {object} trip  the trip as the API returned it
 * @param {{card: HTMLElement, list: HTMLElement, hint: HTMLElement, exportButton: HTMLElement|null, labels: Record<string,string>}} ui
 */
export function renderTripDocuments(trip, ui) {
  const docs = trip.documents;
  const known = docs && typeof docs === 'object';

  // a role with no documents at all, or a cancelled trip that never had any: no card
  const has = known && KINDS.some((k) => docs[k]);
  ui.card.classList.toggle('d-none', !known || (!has && trip.status === 'cancelled'));
  ui.list.innerHTML = '';

  if (!known) return;

  KINDS.forEach((kind) => {
    if (!docs[kind]) return;

    const item = document.createElement('li');
    item.className = 'd-flex align-items-center justify-content-between mb-2';

    const name = document.createElement('span');
    name.textContent = ui.labels[kind] + (docs[kind].number ? ' — ' : '');
    if (docs[kind].number) {
      const number = document.createElement('span');
      number.dir = 'ltr';
      number.textContent = docs[kind].number; // a code, shown as text
      name.appendChild(number);
    }

    const open = document.createElement('span');
    open.innerHTML = window.gcmViewAttachment(docs[kind].url); // an eye icon that opens the PDF in a new tab

    item.append(name, open);
    ui.list.appendChild(item);
  });

  // before the trip is completed there is nothing yet: say when there will be
  ui.hint.classList.toggle('d-none', has || trip.status === 'cancelled');

  if (ui.exportButton) {
    const url = docs.export_url || null;
    ui.exportButton.classList.toggle('d-none', !url);
    ui.exportButton.onclick = url ? () => window.gcmDownload(url, (trip.code || 'trip') + '-documents.pdf') : null;
  }
}
