/**
 * Edit Vehicle page — thin entry over the shared vehicle form engine.
 * operational_status is NOT in this form; it changes via the status card
 * on the details page.
 */
'use strict';

import { initVehicleForm } from './vehicle/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initVehicleForm({ mode: 'edit', id: window.vehicleEditId });
});
