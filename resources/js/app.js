import './bootstrap';
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
