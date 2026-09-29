/**
 * Page loader (resources/views/_partials/page-loader.blade.php).
 *
 * Shown from the first paint, hidden once BOTH are true:
 *   1. the browser fired `load`, and
 *   2. no axios request is in flight — every list / details / edit page fetches its data with axios right
 *      after DOMContentLoaded, so without this the loader would vanish just as the page turns into an
 *      empty card with a spinner.
 * A short debounce absorbs "a request that starts the next one", and a hard timeout guarantees a slow or
 * failed request can never leave the page covered.
 *
 * After it has been hidden once, later requests (filters, searches, saves) never bring it back — those
 * have their own feedback (DataTables "processing", gcmBusy). It does come back when the user clicks a
 * link to another page of the app, so the wait between click and the next paint is visible too.
 *
 * window.gcmPageLoader.show() / .hide() are exposed for the rare page that needs to drive it.
 */
const el = document.getElementById('gcm-page-loader');

if (el) {
  const MAX_WAIT_MS = 15000;
  const SETTLE_MS = 150;

  let pageLoaded = document.readyState === 'complete';
  let inflight = 0;
  let firstHideDone = false;
  let settleTimer;
  let failsafeTimer;

  const hide = () => {
    clearTimeout(settleTimer);
    clearTimeout(failsafeTimer);
    firstHideDone = true;
    el.classList.add('gcm-pl-hide');
    setTimeout(() => {
      if (el.classList.contains('gcm-pl-hide')) el.style.display = 'none';
    }, 300);
  };

  const show = () => {
    clearTimeout(failsafeTimer);
    el.style.display = 'flex';
    // next frame so the opacity transition runs from 0
    requestAnimationFrame(() => el.classList.remove('gcm-pl-hide'));
    failsafeTimer = setTimeout(hide, MAX_WAIT_MS);
  };

  const settle = () => {
    if (firstHideDone || !pageLoaded || inflight > 0) return;
    clearTimeout(settleTimer);
    settleTimer = setTimeout(() => {
      if (inflight === 0) hide();
    }, SETTLE_MS);
  };

  failsafeTimer = setTimeout(hide, MAX_WAIT_MS);

  if (window.axios) {
    window.axios.interceptors.request.use((config) => {
      if (!firstHideDone) {
        config.__gcmLoaderCounted = true;
        inflight++;
      }
      return config;
    });

    const done = (config) => {
      if (config && config.__gcmLoaderCounted) {
        inflight = Math.max(0, inflight - 1);
        settle();
      }
    };

    window.axios.interceptors.response.use(
      (response) => {
        done(response.config);
        return response;
      },
      (error) => {
        done(error && error.config);
        return Promise.reject(error);
      }
    );
  }

  window.addEventListener('load', () => {
    pageLoaded = true;
    settle();
  });

  // Back/forward navigation can restore the page from the bfcache with the loader still up.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) hide();
  });

  // Click through to another page of the app -> show the loader straight away. Only real page routes:
  // /api/... links are file downloads (the page does not change, so the loader would never go away).
  const PAGE_ROUTES = /^\/(app|dashboard|pages|platform|lang)(\/|$)/;

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest && event.target.closest('a[href]');
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;

    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href)) return;

    let url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return;
    }

    if (url.origin !== window.location.origin || !PAGE_ROUTES.test(url.pathname)) return;
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

    show();
  });

  window.gcmPageLoader = { show, hide };
}
