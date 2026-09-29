/**
 * Create Client Company page — thin entry over the shared company form engine.
 */
'use strict';

import { initCompanyForm } from './company/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initCompanyForm({ mode: 'create' });
});
