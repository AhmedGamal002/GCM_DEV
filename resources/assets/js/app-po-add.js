/**
 * Create Purchase Order page — thin entry over the shared purchase-order form engine.
 */
'use strict';

import { initPoForm } from './po/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initPoForm({ mode: 'create' });
});
