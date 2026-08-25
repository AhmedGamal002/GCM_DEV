import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Sanctum SPA cookie mode: same-origin requests automatically carry the
// session cookie and attach X-XSRF-TOKEN from Sanctum's XSRF-TOKEN cookie.
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
