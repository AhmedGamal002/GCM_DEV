/**
 * Create Vehicle page — thin entry over the shared vehicle form engine.
 */
'use strict';

import { initVehicleForm } from './vehicle/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initVehicleForm({ mode: 'create' });
});
