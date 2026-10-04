import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Sanctum SPA cookie mode: same-origin requests automatically carry the
// session cookie and attach X-XSRF-TOKEN from Sanctum's XSRF-TOKEN cookie.
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// A deactivated account is signed out server-side on its next request
// (EnsureTenant answers 401 with code=account_deactivated) — send the user to
// the login page, which shows the reason, instead of leaving them on a page
// whose every action now fails.
window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const data = error && error.response && error.response.data;
    if (error && error.response && error.response.status === 401 && data && data.code === 'account_deactivated') {
      window.location.href = '/login?deactivated=1';
    }
    return Promise.reject(error);
  }
);
