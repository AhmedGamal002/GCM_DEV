/**
 * Edit Asset page — thin entry over the shared asset form engine.
 * Only the name is editable; operational_status changes via the status
 * card on the details page.
 */
'use strict';

import { initAssetForm } from './asset/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initAssetForm({ mode: 'edit', id: window.assetEditId });
});
