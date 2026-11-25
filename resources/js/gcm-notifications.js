/**
 * The notification bell (FRD V01.14: "قائمة الاشعارات — اخر 5 اشعارات مع رابط لصفحة جميع الاشعارات", part 8.2).
 * Ordinary notifications, not instant ones: the latest five and the unread count are read when the page loads and
 * again each time the dropdown opens, from /api/v1/notifications/latest. A notification with a link opens it.
 * Every value is set as text (the sentence comes from the server, written from the notification's own data).
 *
 * `window.gcmNotifications.refresh()` re-reads it — the notifications page calls it after "mark all as read".
 */
document.addEventListener('DOMContentLoaded', function () {
  const bell = document.getElementById('gcm-bell');
  if (!bell || !window.axios) return;

  const list = document.getElementById('gcm-bell-list');
  const dot = document.getElementById('gcm-bell-dot');
  const count = document.getElementById('gcm-bell-count');
  const readAll = document.getElementById('gcm-bell-read-all');
  const empty = list.innerHTML; // the "No notifications yet." block from the page

  const el = (tag, cls, text) => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;

    return node;
  };

  const when = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);

    return d.toLocaleString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
  };

  function render(payload) {
    const rows = payload.data || [];
    const unread = payload.unread_count || 0;

    dot.classList.toggle('d-none', unread === 0);
    count.textContent = unread;
    count.classList.toggle('d-none', unread === 0);
    readAll.classList.toggle('d-none', unread === 0);

    if (!rows.length) {
      list.innerHTML = empty;

      return;
    }

    list.textContent = '';
    rows.forEach((row) => {
      const item = el(row.url ? 'a' : 'div', 'dropdown-item d-block py-3 border-bottom text-wrap' + (row.read ? '' : ' bg-label-primary'));
      if (row.url) item.href = row.url;
      item.appendChild(el('div', 'fw-medium small', row.type_label));
      item.appendChild(el('div', 'small text-body', row.detail));
      item.appendChild(el('div', 'small text-muted', when(row.created_at)));
      list.appendChild(item);
    });
  }

  function refresh() {
    return window.axios
      .get('/api/v1/notifications/latest')
      .then((response) => render(response.data))
      .catch(() => {}); // the bell is a convenience: a failed read leaves it as it was
  }

  readAll.addEventListener('click', function () {
    window.axios.post('/api/v1/notifications/read').then(refresh).catch(() => {});
  });

  bell.addEventListener('show.bs.dropdown', refresh);
  window.gcmNotifications = { refresh };
  refresh();
});
