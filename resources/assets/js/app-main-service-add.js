/**
 * Create Main Service page — thin entry over the shared main-service form engine.
 */
'use strict';

import { initMainServiceForm } from './service/main-form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initMainServiceForm({ mode: 'create' });
});
