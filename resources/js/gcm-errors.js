/**
 * Clear error messages for every failed request — nothing in the app says just "Something went wrong".
 *
 * window.gcmErrorMessage(error) turns any axios error into a sentence the user can act on:
 *   - a rejected form (422) lists what is wrong ("The email has already been taken. ...");
 *   - no permission / not found / session expired / file too big / too many attempts / no connection /
 *     server problem each have their own wording (with the status code for the last two, so support can
 *     be told something concrete).
 * It is also installed as an axios response interceptor that rewrites `error.response.data.message` to
 * that sentence (and gives a connection failure a synthetic `response`), so every existing handler that
 * shows `error.response.data.message` gets the clear text without being touched.
 *
 * The wording comes from window.gcmI18n (commonMaster.blade.php), so it follows the language switcher.
 */
const i18n = () => window.gcmI18n || {};
const fill = (text, code) => (text || '').replace(':code', code);

const fromErrors = (errors) => {
  const seen = new Set();
  Object.values(errors || {})
    .flat()
    .forEach((m) => seen.add(m));
  return Array.from(seen).join(' ');
};

window.gcmErrorMessage = function (error) {
  const t = i18n();
  const res = error && error.response;

  // No response at all (offline, server down, blocked) — also the synthetic status-0 response the
  // interceptor below attaches to such errors.
  if (!res || res.status === 0) {
    return !res && error && error.message && error.message !== 'Network Error' ? error.message : t.network;
  }

  const data = res.data || {};
  // A 404's own text is a framework / model-class sentence ("No query results for model ...") — never useful.
  if (res.status === 404) return t.not_found;
  if (res.status === 422 && data.errors) return fromErrors(data.errors) || t.invalid;

  // A server-written message is already specific (a policy 403, "domain" 422s) — keep it, except the
  // framework's stock phrases, which say nothing.
  const stock = ['Server Error', 'Forbidden', 'Not Found', 'Unauthenticated.', 'This action is unauthorized.', 'Too Many Attempts.'];
  if (data.message && !stock.includes(data.message) && res.status < 500) return data.message;

  switch (res.status) {
    case 401:
      return t.session_expired;
    case 403:
      return t.forbidden;
    case 404:
      return t.not_found;
    case 413:
      return t.too_large;
    case 419:
      return t.csrf;
    case 422:
      return data.message || t.invalid;
    case 429:
      return t.too_many;
    default:
      return res.status >= 500 ? fill(t.server, res.status) : data.message || fill(t.unknown_status, res.status);
  }
};

if (window.axios) {
  window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
      if (!error.response && error.config) {
        error.response = { status: 0, data: {} };
      }
      if (error.response) {
        const data = error.response.data;
        // Only touch JSON-ish bodies; a downloaded file (Blob) keeps its own shape.
        if (data === undefined || data === null || (typeof data === 'object' && !(data instanceof Blob))) {
          error.response.data = Object.assign({}, data || {}, { message: window.gcmErrorMessage(error) });
        }
      }
      return Promise.reject(error);
    }
  );
}
