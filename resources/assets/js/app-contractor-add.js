/**
 * Create Contractor page — thin entry over the shared contractor form engine.
 */
'use strict';

import { initContractorForm } from './contractor/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initContractorForm({ mode: 'create' });
});
