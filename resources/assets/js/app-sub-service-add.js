/**
 * Create Sub-service page — thin entry over the shared sub-service form engine.
 */
'use strict';

import { initSubServiceForm } from './service/sub-form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initSubServiceForm({ mode: 'create' });
});
