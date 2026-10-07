/**
 * Edit Facility page — thin entry over the shared facility form engine.
 * The prefix, the environmental service and the recycling efficiency are
 * locked after creation (FRD §1.8: name + optional data only);
 * operational_status changes via the status card.
 */
'use strict';

import { initFacilityForm } from './facility/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initFacilityForm({ mode: 'edit', id: window.facilityEditId });
});
