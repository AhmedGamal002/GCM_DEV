/**
 * Edit asset capacity category — thin entry over the shared engine.
 * Only the name and the additional data are editable (FRD §1.7.2 says name only; the client kept the notes open).
 */
'use strict';

import { initAssetCategoryForm } from './asset-category/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initAssetCategoryForm({ mode: 'edit', id: window.assetCategoryEditId });
});
