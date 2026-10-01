/**
 * Edit Client Account page — thin entry over the shared client-account form engine.
 */
'use strict';

import { initClientUserForm } from './client-user/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initClientUserForm({ mode: 'edit', id: window.clientUserEditId });
});
