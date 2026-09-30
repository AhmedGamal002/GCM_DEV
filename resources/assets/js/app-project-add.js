/**
 * Create Project page — thin entry over the shared project form engine.
 */
'use strict';

import { initProjectForm } from './project/form-core.js';

document.addEventListener('DOMContentLoaded', () => {
  initProjectForm({ mode: 'create' });
});
