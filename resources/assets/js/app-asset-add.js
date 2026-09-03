/**
 * Create Asset page — thin entry over the shared asset form engine.
 */
'use strict';

import { initAssetForm } from './asset/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initAssetForm({ mode: 'create' });
});
