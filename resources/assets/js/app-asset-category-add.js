/**
 * Create asset capacity category — thin entry over the shared engine.
 */
'use strict';

import { initAssetCategoryForm } from './asset-category/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initAssetCategoryForm({ mode: 'create' });
});
