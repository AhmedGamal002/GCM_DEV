import './bootstrap';
import './busy';
import './page-loader';
import './gcm-select2';
/*
  Add custom scripts here
*/
import.meta.glob([
  '../assets/img/**',
  // '../assets/json/**',
  '../assets/vendor/fonts/**'
]);

// Tenant-user logout via the API — same /api/v1/auth/logout endpoint the
// mobile app will use later. See navbar.blade.php's #api-logout-link.
document.addEventListener('DOMContentLoaded', function () {
  const logoutLink = document.getElementById('api-logout-link');

  if (logoutLink) {
    logoutLink.addEventListener('click', function () {
      window.axios.post('/api/v1/auth/logout').finally(function () {
        window.location.href = '/login';
      });
    });
  }
});

// Navbar current date & time (FRD: topbar shows the current date and time
// live) — see navbar.blade.php's #navbar-datetime.
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('navbar-datetime');
  if (!el) return;

  // Platform (Super Admin) pages stay English regardless of the tenant-side
  // session locale — see CLAUDE.md's Platform-pages-are-always-English rule.
  const isPlatform = window.location.pathname.startsWith('/platform');
  const locale = !isPlatform && document.documentElement.lang === 'ar' ? 'ar-EG' : 'en-US';

  function render() {
    el.textContent = new Date().toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' });
  }

  render();
  setInterval(render, 30000);
});
