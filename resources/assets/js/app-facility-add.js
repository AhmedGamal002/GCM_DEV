/**
 * Create Facility page — thin entry over the shared facility form engine.
 */
'use strict';

import { initFacilityForm } from './facility/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initFacilityForm({ mode: 'create' });
});
