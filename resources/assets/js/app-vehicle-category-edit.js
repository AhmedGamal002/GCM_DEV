/**
 * Edit vehicle category — thin entry over the shared engine.
 */
'use strict';

import { initVehicleCategoryForm } from './vehicle-category/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initVehicleCategoryForm({ mode: 'edit', id: window.vehicleCategoryEditId });
});
