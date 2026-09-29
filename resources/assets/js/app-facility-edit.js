/**
 * Edit Facility page — thin entry over the shared facility form engine.
 * The prefix is locked; the environmental service + recycling efficiency
 * lock too once the facility is in use; operational_status changes via the
 * status card on the details page.
 */
'use strict';

import { initFacilityForm } from './facility/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initFacilityForm({ mode: 'edit', id: window.facilityEditId });
});
