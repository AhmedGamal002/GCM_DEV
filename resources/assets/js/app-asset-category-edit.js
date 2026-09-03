/**
 * Edit asset capacity category — thin entry over the shared engine.
 * Only the name is editable (FRD §1.7.2).
 */
'use strict';

import { initAssetCategoryForm } from './asset-category/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initAssetCategoryForm({ mode: 'edit', id: window.assetCategoryEditId });
});
