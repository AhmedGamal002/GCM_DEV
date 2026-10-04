/**
 * Create Contractor User page — thin entry over the shared contractor-user form engine.
 */
'use strict';

import { initContractorUserForm } from './contractor-user/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initContractorUserForm({ mode: 'create' });
});
