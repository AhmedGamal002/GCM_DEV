// Builds docs/acceptance/checklist.html from every week-*.md acceptance file.
// Run from the project root:  node docs/acceptance/build-checklist.mjs
// The .md files stay the single source of truth — never edit checklist.html by hand.

import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildUat } from './build-uat.mjs';

const dir = dirname(fileURLToPath(import.meta.url));

// Also escapes `"` — this string is used both as inner-HTML text AND as
// data-id/data-title ATTRIBUTE values below; an unescaped quote there
// truncates the attribute at that point (e.g. a title containing a quoted
// phrase silently lost everything after the first `"`).
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const inline = s =>
  esc(s)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

const FIELD_LABELS = {
  FRD: 'مرجع الـFRD',
  الدور: 'الدور',
  الخطوات: 'الخطوات',
  المتوقع: 'النتيجة المتوقعة',
  'يغطيه آليًا': 'يغطيه آليًا',
};

function parseFile(file) {
  const lines = readFileSync(join(dir, file), 'utf8').split(/\r?\n/);
  const key = file.replace(/\.md$/, '');
  const doc = { key, title: '', sync: '', sections: [] };
  let section = null;
  let item = null;
  let field = null;

  for (const raw of lines) {
    if (raw.startsWith('# ') && !doc.title) {
      doc.title = raw.slice(2).trim();
    } else if (raw.startsWith('> آخر مزامنة')) {
      doc.sync = raw.replace(/^>\s*/, '').replace(/\*\*/g, '').split('·')[0].trim();
    } else if (raw.startsWith('## ')) {
      section = { title: raw.slice(3).trim(), items: [] };
      doc.sections.push(section);
      item = field = null;
    } else if (raw.startsWith('### ')) {
      const m = raw.slice(4).match(/^(\d+(?:\.\d+)?)\.?\s+(.*)$/);
      if (!m) continue;
      if (!section) {
        section = { title: doc.title, items: [] };
        doc.sections.push(section);
      }
      item = { id: `${key}#${m[1]}`, num: m[1], title: m[2], fields: [] };
      section.items.push(item);
      field = null;
    } else if (item) {
      const f = raw.match(/^- \*\*(.+?):\*\*\s*(.*)$/);
      const sub = raw.match(/^\s{2,}- (.*)$/);
      if (f) {
        if (f[1] === 'النتيجة') {
          field = null;
        } else {
          field = { key: f[1], text: f[2], subs: [] };
          item.fields.push(field);
        }
      } else if (sub && field) {
        field.subs.push(sub[1]);
      }
    }
  }
  return doc;
}

const files = readdirSync(dir).filter(f => /^week-.*\.md$/.test(f)).sort();
const docs = files.map(parseFile);
const total = docs.reduce((n, d) => n + d.sections.reduce((m, s) => m + s.items.length, 0), 0);

const renderItem = it => {
  const rows = it.fields
    .map(f => {
      const subs = f.subs.length ? `<ul>${f.subs.map(s => `<li>${inline(s)}</li>`).join('')}</ul>` : '';
      return `<div class="row"><dt>${FIELD_LABELS[f.key] || esc(f.key)}</dt><dd>${inline(f.text)}${subs}</dd></div>`;
    })
    .join('');

  return `
    <article class="item" data-id="${esc(it.id)}" data-title="${esc(it.title)}">
      <header>
        <label class="check"><input type="checkbox" class="pass" aria-label="تم">
          <span class="num">${esc(it.num)}</span></label>
        <h3>${inline(it.title)}</h3>
        <button type="button" class="failbtn" title="علّم إن البند فشل">فشل</button>
      </header>
      <dl>${rows}</dl>
      <textarea class="note" rows="2" placeholder="ملاحظات — لو فشل: اكتب اللي حصل فعليًا"></textarea>
    </article>`;
};

// Shared by the admin checklist and the client one below — a doc/section/item
// tree in this shape renders the same way regardless of which fields survived
// filtering (a client item simply arrives with no "يغطيه آليًا" field, no
// special-casing needed here).
function renderDocs(docsArr) {
  return docsArr
    .map(
      d => `
  <section class="doc" data-doc="${esc(d.key)}">
    ${d.title ? `<h2 class="doc-title">${esc(d.title)}${d.sync ? `<small>${esc(d.sync)}</small>` : ''}</h2>` : ''}
    ${d.note ? `<p class="doc-note">${inline(d.note)}</p>` : ''}
    ${d.sections
      .map(
        s => `
    <details open class="group">
      <summary>${inline(s.title)} <span class="count"></span></summary>
      ${s.items.map(renderItem).join('')}
    </details>`,
      )
      .join('')}
  </section>`,
    )
    .join('\n');
}

const body = renderDocs(docs);

const generated = new Date().toISOString().slice(0, 10);

const html = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GCM Portal — Checklist الاختبار</title>
<style>
  :root {
    --bg:#f6f7fb; --card:#fff; --text:#1f2430; --muted:#69708a; --line:#e3e6ef;
    --accent:#4f46e5; --ok:#15803d; --ok-bg:#e6f6ec; --bad:#b91c1c; --bad-bg:#fdeaea; --code:#eef0f8;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#14161f; --card:#1d2030; --text:#e7e9f3; --muted:#9aa1bd; --line:#2d3148;
      --accent:#8b85ff; --ok:#4ade80; --ok-bg:#15301f; --bad:#f87171; --bad-bg:#3a1c1c; --code:#272b40; }
  }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); line-height:1.7;
    font-family:"Segoe UI",Tahoma,"Noto Naskh Arabic",Arial,sans-serif; }
  code { background:var(--code); padding:1px 6px; border-radius:5px; font-size:.88em;
    direction:ltr; unicode-bidi:isolate; font-family:Consolas,monospace; overflow-wrap:anywhere; word-break:break-word; }
  dt, dd, .row, h3 { min-width:0; overflow-wrap:anywhere; }
  .wrap { max-width:960px; margin:0 auto; padding:0 16px 80px; }
  h1 { font-size:1.5rem; margin:28px 0 4px; }
  .sub { color:var(--muted); margin:0 0 20px; }

  .setup { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 18px; margin-bottom:18px; }
  .setup summary { cursor:pointer; font-weight:700; }
  .setup ol { margin:10px 0; padding-inline-start:22px; }
  .setup table { width:100%; border-collapse:collapse; margin-top:8px; font-size:.92rem; }
  .setup th, .setup td { text-align:start; padding:5px 8px; border-bottom:1px solid var(--line); }

  .bar { background:var(--bg); padding:12px 0 10px; border-bottom:1px solid var(--line); }
  @media (min-width:720px) { .bar { position:sticky; top:0; z-index:5; } }
  .stats { display:flex; gap:14px; flex-wrap:wrap; align-items:center; font-size:.95rem; }
  .stats b { font-size:1.1rem; }
  .prog { height:9px; background:var(--line); border-radius:9px; overflow:hidden; margin:8px 0; display:flex; }
  .prog i { display:block; height:100%; }
  .prog .p { background:var(--ok); } .prog .f { background:var(--bad); }
  .tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .tools input[type=search], .tools input[type=text] { padding:6px 10px; border:1px solid var(--line); border-radius:8px;
    background:var(--card); color:var(--text); font:inherit; min-width:150px; }
  .chip, .btn { border:1px solid var(--line); background:var(--card); color:var(--text); padding:5px 12px;
    border-radius:20px; cursor:pointer; font:inherit; font-size:.9rem; }
  .chip.on { background:var(--accent); border-color:var(--accent); color:#fff; }
  .btn:hover, .chip:hover { border-color:var(--accent); }

  .doc-title { font-size:1.15rem; margin:30px 0 6px; padding-bottom:6px; border-bottom:2px solid var(--accent); }
  .doc-title small { display:block; font-size:.78rem; font-weight:400; color:var(--muted); }
  .group { margin:12px 0; }
  .group > summary { cursor:pointer; font-weight:700; padding:8px 4px; }
  .count { font-weight:400; color:var(--muted); font-size:.85rem; }

  .item { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px 16px; margin:10px 0;
    border-inline-start:5px solid var(--line); }
  .item.is-pass { border-inline-start-color:var(--ok); background:linear-gradient(var(--ok-bg),var(--ok-bg)) ; }
  .item.is-fail { border-inline-start-color:var(--bad); background:linear-gradient(var(--bad-bg),var(--bad-bg)); }
  .item header { display:flex; align-items:center; gap:10px; }
  .item h3 { font-size:1rem; margin:0; flex:1; }
  .check { display:flex; align-items:center; gap:8px; cursor:pointer; }
  .check input { width:22px; height:22px; accent-color:var(--ok); cursor:pointer; }
  .num { background:var(--code); border-radius:8px; padding:0 8px; font-weight:700; font-size:.85rem; direction:ltr; }
  .failbtn { border:1px solid var(--bad); color:var(--bad); background:transparent; border-radius:8px; padding:3px 12px; cursor:pointer; font:inherit; font-size:.85rem; }
  .item.is-fail .failbtn { background:var(--bad); color:#fff; }
  dl { margin:10px 0 6px; }
  .row { display:grid; grid-template-columns:130px 1fr; gap:6px; padding:5px 0; border-top:1px dashed var(--line); }
  dt { color:var(--muted); font-size:.85rem; padding-top:2px; }
  dd { margin:0; } dd ul { margin:4px 0 0; padding-inline-start:20px; }
  .note { width:100%; resize:vertical; border:1px solid var(--line); border-radius:8px; background:var(--bg);
    color:var(--text); padding:6px 10px; font:inherit; font-size:.9rem; }
  .item.hidden, .group.hidden, .doc.hidden { display:none; }
  .foot { color:var(--muted); font-size:.82rem; margin-top:30px; }
  @media (max-width:640px) { .row { grid-template-columns:1fr; gap:0; } }
  @media print { .bar, .tools, .setup { display:none; } .item { break-inside:avoid; } body { background:#fff; color:#000; } }
</style>
</head>
<body>
<div class="wrap">
  <h1>GCM Portal — Checklist الاختبار</h1>
  <p class="sub">المرحلة الأولى (أسابيع 1–3) · كل بند = خطوات + النتيجة المتوقعة. علّم ✔ على اللي خلّصته و"فشل" على اللي طلع غلط. تقدّمك بيتحفظ تلقائيًا في المتصفح ده.</p>

  <details class="setup" open>
    <summary>تجهيز البيئة قبل ما تبدأ</summary>
    <ol>
      <li>من جذر المشروع: <code>php artisan test</code> — لازم كله أخضر.</li>
      <li>بيانات نظيفة: <code>php artisan migrate:fresh --seed</code></li>
      <li>شغّل السيرفر: <code>php artisan serve</code> وافتح <code>http://localhost:8000</code></li>
      <li>آخر الجلسة: <code>php artisan migrate:fresh --seed</code> تاني عشان الداتا ترجع نضيفة.</li>
    </ol>
    <table>
      <tr><th>الحساب</th><th>الدور</th><th>الدخول</th></tr>
      <tr><td><code>admin@gcm.test</code></td><td>مدير النظام</td><td><code>/login</code></td></tr>
      <tr><td><code>dataentry@gcm.test</code></td><td>مدخل البيانات</td><td><code>/login</code></td></tr>
      <tr><td><code>auditor@gcm.test</code></td><td>مراقب النظام</td><td><code>/login</code></td></tr>
      <tr><td><code>driver@gcm.test</code></td><td>سائق</td><td><code>/login</code></td></tr>
      <tr><td><code>superadmin@product.test</code></td><td>Super Admin</td><td><code>/platform/login</code></td></tr>
    </table>
    <p class="sub" style="margin:8px 0 0">كلمة السر لكل الحسابات: <code>password</code></p>
  </details>

  <div class="bar">
    <div class="stats">
      <span>تم: <b id="s-pass">0</b></span>
      <span>فشل: <b id="s-fail" style="color:var(--bad)">0</b></span>
      <span>متبقي: <b id="s-left">0</b></span>
      <span>من <b>${total}</b> بند</span>
    </div>
    <div class="prog"><i class="p" id="b-pass"></i><i class="f" id="b-fail"></i></div>
    <div class="tools">
      <input type="text" id="tester" placeholder="اسم المُختبِر">
      <button type="button" class="chip on" data-f="all">الكل</button>
      <button type="button" class="chip" data-f="left">المتبقي</button>
      <button type="button" class="chip" data-f="pass">تم</button>
      <button type="button" class="chip" data-f="fail">فشل</button>
      <input type="search" id="q" placeholder="بحث…">
      <button type="button" class="btn" id="export">نسخ تقرير النتيجة</button>
      <button type="button" class="btn" id="reset">مسح كل العلامات</button>
    </div>
  </div>
${body}
  <p class="foot">اتولّد تلقائيًا من ملفات <code>docs/acceptance/week-*.md</code> بتاريخ ${generated} — للتحديث: <code>node docs/acceptance/build-checklist.mjs</code>. متعدّلش الملف ده بإيدك.</p>
</div>

<script>
(function () {
  var KEY = 'gcm-checklist-v1';
  var state = {};
  try { state = JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) {}
  state.items = state.items || {};
  var items = [].slice.call(document.querySelectorAll('.item'));
  var filter = 'all';

  function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
  function get(id) { return state.items[id] || (state.items[id] = { s: '', n: '' }); }

  function paint(el) {
    var st = get(el.dataset.id);
    el.classList.toggle('is-pass', st.s === 'pass');
    el.classList.toggle('is-fail', st.s === 'fail');
    el.querySelector('.pass').checked = st.s === 'pass';
    el.querySelector('.note').value = st.n || '';
  }

  function refresh() {
    var p = 0, f = 0, q = document.getElementById('q').value.trim().toLowerCase();
    items.forEach(function (el) {
      var s = get(el.dataset.id).s;
      if (s === 'pass') p++; else if (s === 'fail') f++;
      var show = filter === 'all' || (filter === 'left' && !s) || filter === s;
      if (show && q) show = el.textContent.toLowerCase().indexOf(q) !== -1 || el.dataset.id.indexOf(q) !== -1;
      el.classList.toggle('hidden', !show);
    });
    document.querySelectorAll('.group').forEach(function (g) {
      var its = [].slice.call(g.querySelectorAll('.item'));
      var done = its.filter(function (i) { return get(i.dataset.id).s === 'pass'; }).length;
      g.querySelector('.count').textContent = '(' + done + '/' + its.length + ')';
      g.classList.toggle('hidden', its.every(function (i) { return i.classList.contains('hidden'); }));
    });
    document.querySelectorAll('.doc').forEach(function (d) {
      d.classList.toggle('hidden', [].every.call(d.querySelectorAll('.group'), function (g) { return g.classList.contains('hidden'); }));
    });
    var n = items.length;
    document.getElementById('s-pass').textContent = p;
    document.getElementById('s-fail').textContent = f;
    document.getElementById('s-left').textContent = n - p - f;
    document.getElementById('b-pass').style.width = (p / n * 100) + '%';
    document.getElementById('b-fail').style.width = (f / n * 100) + '%';
  }

  items.forEach(function (el) {
    paint(el);
    el.querySelector('.pass').addEventListener('change', function (e) {
      get(el.dataset.id).s = e.target.checked ? 'pass' : ''; save(); paint(el); refresh();
    });
    el.querySelector('.failbtn').addEventListener('click', function () {
      var st = get(el.dataset.id); st.s = st.s === 'fail' ? '' : 'fail'; save(); paint(el); refresh();
    });
    el.querySelector('.note').addEventListener('input', function (e) {
      get(el.dataset.id).n = e.target.value; save();
    });
  });

  document.querySelectorAll('.chip').forEach(function (c) {
    c.addEventListener('click', function () {
      document.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('on'); });
      c.classList.add('on'); filter = c.dataset.f; refresh();
    });
  });
  document.getElementById('q').addEventListener('input', refresh);

  var tester = document.getElementById('tester');
  tester.value = state.tester || '';
  tester.addEventListener('input', function () { state.tester = tester.value; save(); });

  document.getElementById('reset').addEventListener('click', function () {
    if (!confirm('هيتمسح كل التعليم والملاحظات. متأكد؟')) return;
    state.items = {}; save(); items.forEach(paint); refresh();
  });

  document.getElementById('export').addEventListener('click', function () {
    var out = ['تقرير اختبار GCM Portal — ' + (tester.value || 'بدون اسم') + ' — ' + new Date().toLocaleDateString('ar-EG'), ''];
    var p = 0, f = 0, left = [];
    items.forEach(function (el) {
      var st = get(el.dataset.id);
      if (st.s === 'pass') p++;
      else if (st.s === 'fail') { f++; out.push('❌ [' + el.dataset.id + '] ' + el.dataset.title + (st.n ? '\\n   ' + st.n.replace(/\\n/g, '\\n   ') : '')); }
      else left.push(el.dataset.id);
    });
    out.splice(2, 0, 'تم: ' + p + ' | فشل: ' + f + ' | متبقي: ' + left.length + ' | من ' + items.length, '');
    if (left.length) out.push('', 'لسه متختبرش: ' + left.join('، '));
    var text = out.join('\\n');
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(
      function () { alert('اتنسخ التقرير — ابعته للمطوّر.'); },
      function () { prompt('انسخ التقرير:', text); }
    );
  });

  refresh();
})();
</script>
</body>
</html>
`;

writeFileSync(join(dir, 'checklist.html'), html);
console.log(`checklist.html built — ${total} items from ${files.length} files`);

// ---------------------------------------------------------------------------
// client-checklist.html — a curated subset for the CLIENT's own tester: only
// items fully testable while logged in as System Admin, nothing that assumes
// a second login (data_entry/auditor/driver), a second tenant, Super Admin,
// or Postman. Deliberately an ALLOWLIST (CLIENT_INCLUDE), not an exclude
// list — most of what's left out needed exactly one of those, so a new item
// added to a week-*.md file is left out by default until someone reviews it
// and adds its id here. CLIENT_OVERRIDES rewrites just the title/steps/
// expected text for included items whose original wording assumed a login
// this document doesn't hand out (e.g. "log in as the auditor to see the
// message" becomes "admin changes the status and the row updates").
//
// This file must never show that it's generated, reference internal week/
// phase numbers, FRD sync dates, or automated-test class names — the client
// only sees what to click and what should happen.

// Reorganized module-by-module (View → Create → Edit → Status → business
// rules), not by which week-*.md file or discovery order an item happened to
// come from — a tester reading source-ordered "gap review" history has no way
// to tell "did I check every CRUD action for Users?" A flat allowlist can't
// express that grouping, so CLIENT_GROUPS both selects AND orders: each
// group's `ids` list is a curated, explicit set (same allowlist philosophy as
// before — a new source item is invisible here until someone adds its id to
// the right group), and its position in the array is the section's position
// in the page. Every included id must still resolve to a real parsed item —
// buildClientDocs throws if one doesn't, so a typo or a renumbered source
// item is caught immediately instead of silently vanishing.
const CLIENT_GROUPS = [
  { title: 'المستخدمين', ids: ['week-1-2-auth-users-roles#8', 'week-1-2-auth-users-roles#20', 'week-1-2-auth-users-roles#22', 'week-1-2-auth-users-roles#21', 'week-1-2-auth-users-roles#10', 'week-1-2-auth-users-roles#11', 'week-1-2-auth-users-roles#12'] },
  { title: 'حسابات العملاء', ids: ['week-5-client-accounts#1', 'week-5-client-accounts#2', 'week-5-client-accounts#3', 'week-5-client-accounts#4', 'week-5-client-accounts#5', 'week-5-client-accounts#6', 'week-5-client-accounts#7', 'week-5-client-accounts#8', 'week-5-client-accounts#9', 'week-5-client-accounts#10', 'week-5-client-accounts#11', 'week-5-client-accounts#12', 'week-5-client-accounts#13', 'week-5-client-accounts#17'] },
  { title: 'السائقين', ids: ['week-3-vehicles-drivers-assets#10', 'week-3-vehicles-drivers-assets#11', 'week-3-vehicles-drivers-assets#23', 'week-3-vehicles-drivers-assets#24', 'week-3-frd-gap-review#9', 'week-3-vehicles-drivers-assets#12', 'week-3-vehicles-drivers-assets#13', 'week-3-vehicles-drivers-assets#14', 'week-3-vehicles-drivers-assets#15', 'week-3-vehicles-drivers-assets#28'] },
  { title: 'المركبات', ids: ['week-3-vehicles-drivers-assets#1', 'week-3-vehicles-drivers-assets#2', 'week-3-vehicles-drivers-assets#25', 'week-3-vehicles-drivers-assets#6', 'week-3-vehicles-drivers-assets#3', 'week-3-vehicles-drivers-assets#4', 'week-3-vehicles-drivers-assets#5', 'week-3-vehicles-drivers-assets#8'] },
  { title: 'تصنيفات المركبات', ids: ['week-3-frd-gap-review#10', 'week-3-frd-gap-review#11', 'week-3-frd-gap-review#13', 'week-3-frd-gap-review#14', 'week-3-frd-gap-review#12', 'week-3-frd-gap-review#17', 'week-3-frd-gap-review#19'] },
  { title: 'الأصول', ids: ['week-3-vehicles-drivers-assets#16', 'week-3-vehicles-drivers-assets#26', 'week-3-vehicles-drivers-assets#18', 'week-3-vehicles-drivers-assets#27', 'week-3-vehicles-drivers-assets#17', 'week-3-vehicles-drivers-assets#19'] },
  { title: 'سعات الأصول', ids: ['week-3-vehicles-drivers-assets#20'] },
  { title: 'المنشآت الوسيطة', ids: ['week-5-facilities#2', 'week-5-facilities#4', 'week-5-facilities#5', 'week-5-facilities#6', 'week-5-facilities#7', 'week-5-facilities#8', 'week-5-facilities#9', 'week-5-facilities#11', 'week-5-facilities#12'] },
  { title: 'الخدمات', ids: ['week-5-services#2', 'week-5-services#4', 'week-5-services#5', 'week-5-services#6', 'week-5-services#7', 'week-5-services#8', 'week-5-services#9', 'week-5-services#10', 'week-5-services#11', 'week-5-services#12', 'week-5-services#13', 'week-5-services#14', 'week-5-services#15', 'week-5-services#16'] },
  { title: 'المتعهدين', ids: ['week-5-contractors#2', 'week-5-contractors#4', 'week-5-contractors#5', 'week-5-contractors#6', 'week-5-contractors#7', 'week-5-contractors#8', 'week-5-contractors#9', 'week-5-contractors#10', 'week-5-contractors#11', 'week-5-contractors#12', 'week-5-contractors#13', 'week-5-contractors#14'] },
  { title: 'مستخدمو المتعهدين', ids: ['week-5-contractor-users#1', 'week-5-contractor-users#2', 'week-5-contractor-users#3', 'week-5-contractor-users#4', 'week-5-contractor-users#5', 'week-5-contractor-users#6', 'week-5-contractor-users#7', 'week-5-contractor-users#8', 'week-5-contractor-users#9'] },
  { title: 'شركات العملاء', ids: ['week-4-client-companies#1', 'week-4-client-companies#2', 'week-4-client-companies#3', 'week-4-client-companies#4', 'week-4-client-companies#5', 'week-4-client-companies#6', 'week-4-client-companies#7', 'week-4-client-companies#8', 'week-4-client-companies#9'] },
  { title: 'المشروعات', ids: ['week-5-projects#2', 'week-5-projects#3', 'week-5-projects#4', 'week-5-projects#5', 'week-5-projects#6', 'week-5-projects#7', 'week-5-projects#9', 'week-5-projects#10', 'week-5-projects#11', 'week-5-projects#12', 'week-5-projects#13', 'week-5-projects#15', 'week-5-projects#16', 'week-5-projects#17', 'week-5-projects#21'] },
  { title: 'التعاقدات', ids: ['week-6-purchase-orders#2', 'week-6-purchase-orders#3', 'week-6-purchase-orders#4', 'week-6-purchase-orders#5', 'week-6-purchase-orders#6', 'week-6-purchase-orders#7', 'week-6-purchase-orders#8', 'week-6-purchase-orders#9', 'week-6-purchase-orders#10', 'week-6-purchase-orders#11', 'week-6-purchase-orders#12', 'week-6-purchase-orders#13', 'week-6-purchase-orders#15', 'week-6-purchase-orders#16', 'week-6-purchase-orders#17'] },
  { title: 'الرحلات', ids: ['week-7-trips#2', 'week-7-trips#3', 'week-7-trips#4', 'week-7-trips#5', 'week-7-trips#6', 'week-7-trips#7', 'week-7-trips#8', 'week-7-trips#9', 'week-7-trips#10', 'week-7-trips#11', 'week-7-trips#12', 'week-7-trips#15', 'week-7-trips#16', 'week-7-trips#17', 'week-7-trips#24', 'week-7-trips#25', 'week-7-trips#26', 'week-7-trips#27', 'week-7-trips#28', 'week-7-trips#29'] },
  { title: 'التقارير', ids: ['week-8-reports#2', 'week-8-reports#3', 'week-8-reports#4', 'week-8-reports#5', 'week-8-reports#6'] },
  { title: 'لوحة التحكم', ids: ['week-8-dashboard#1', 'week-8-dashboard#2', 'week-8-dashboard#3', 'week-8-dashboard#4', 'week-8-dashboard#5', 'week-8-dashboard#6', 'week-8-dashboard#7'] },
  { title: 'الإشعارات', ids: ['week-8-notifications#1', 'week-8-notifications#2', 'week-8-notifications#4', 'week-8-notifications#5', 'week-8-notifications#6', 'week-8-notifications#7', 'week-8-notifications#9'] },
  { title: 'الإعدادات العامة وتنبيهات الوثائق', ids: ['week-5-document-expiry#1', 'week-5-document-expiry#2', 'week-5-document-expiry#3', 'week-5-document-expiry#4', 'week-5-document-expiry#6', 'week-5-document-expiry#7'] },
  { title: 'الملف الشخصي', ids: ['week-1-2-auth-users-roles#2', 'week-3-frd-gap-review#4'] },
  { title: 'قواعد عامة عبر الموديولات', ids: ['week-3-frd-gap-review#5', 'week-3-frd-gap-review#6', 'week-3-frd-gap-review#7'] },
  { title: 'الواجهة والتنقل', ids: ['week-1-2-auth-users-roles#1', 'week-1-2-auth-users-roles#17', 'week-1-2-auth-users-roles#18', 'week-3-frd-gap-review#1', 'week-3-frd-gap-review#2', 'week-3-vehicles-drivers-assets#22', 'week-3-frd-gap-review#30'] },
  { title: 'الأداء والتصدير', ids: ['week-3-frd-gap-review#22', 'week-3-frd-gap-review#23', 'week-3-frd-gap-review#24', 'week-3-frd-gap-review#25', 'week-3-frd-gap-review#29'] },
];

const CLIENT_OVERRIDES = {
  'week-5-document-expiry#7': {
    title: 'فتح مرفقات السائق والمركبة بأيقونة العين',
    stepsList: [
      'افتح صفحة عرض سائق ومركبة ليهم مرفقات، ومرّر الماوس على أيقونة المرفق في جدولَي الوثائق والتصاريح، واضغط عليها.',
      'افتح صفحة تعديل السائق والمركبة، واضغط على المرفق الحالي جنب أي وثيقة أو تصريح.',
      'بدّل اللغة للعربي وكرّر.',
    ],
    expected:
      'مفيش أي أيقونة تنزيل؛ مكانها **أيقونة عين** وتلميح "View" (بالعربي "عرض")، والضغط عليها بيفتح الملف في **تبويب جديد** بعارض المتصفح من غير ما يتنزّل لوحده — في السائق والمركبة بنفس الشكل، وفي صفحتَي العرض والتعديل. لو عايز تحمّله، تحمّله من زر التنزيل في عارض المتصفح نفسه. الخانة اللي مالهاش مرفق مفيهاش أيقونة.',
  },
  'week-5-contractor-users#3': {
    stepsList: ['حاول تنشئ مستخدم متعهد ببريد مستخدم بالفعل (مثلًا `admin@gcm.test`).'],
    expected: 'البريد المكرر بيترفض برسالة واضحة تحت الحقل ومفيش حساب بيتنشأ.',
  },
  'week-5-contractor-users#7': {
    stepsList: ['من صفحة تفاصيل المستخدم غيّر حالته لـ"On Vacation" ثم "Deactivated" بزر "Update Status".'],
    expected: 'الحالة بتتغيّر في الصفحة وفي القايمة، وبتتطلب تأكيد قبل زر "Update Status".',
  },
  'week-5-contractors#4': {
    expected:
      'عنوان الصفحة "إنشاء شركة متعهد جديدة" والفورم كروت: البيانات الأساسية (الاسم*، الاسم المختصر*، الاسم التجاري، الشعار، الهاتف، العنوان، رابط الخريطة، الحالة التشغيلية* وافتراضيها "نشط") ثم الخدمات المعتمدة ثم العقد ثم السجل التجاري والضريبي ثم البيانات الإضافية. الفورم الفاضي بيطلّع "This field is required" تحت الاسم والاسم المختصر. بعد الحفظ بترجع للقايمة برسالة نجاح، والمتعهد الجديد رقمه التعريفي `TST-C0004` (الحروف بتتحوّل لكبيرة تلقائي).',
  },
  'week-5-contractors#2': {
    expected:
      'أربع كروت: المتعهدين (الإجمالي + عدد النشطين)، المركبات، الأصول (حاويات وصهاريج)، السائقين — والثلاثة الأخيرة بتعدّ اللي بيوفّره المتعهدون بس. الجدول: الرقم التعريفي / المتعهد (والاسم التجاري تحته) / السائقين / المركبات / الحاويات / الصهاريج / الحالة / عين + قلم. البحث بيشمل الاسم والاسم التجاري والاسم المختصر والرقم التعريفي. الفلتر والترقيم والترتيب شغالين.',
  },
  'week-5-projects#13': {
    title: 'قواعد إدراج أصل في مشروع',
    stepsList: [
      'افتح صفحة "Insert asset into project" واختار شركة ومشروع.',
      'بص على قائمة الأصول: لاحظ إن الأصل المدرج بالفعل في مشروع، والأصل "في الصيانة" (Container A-03) مش موجودين فيها.',
      'اختار الشركة Gulf Petrochemicals وبص على قائمة مشروعاتها.',
    ],
    expected:
      'قائمة الأصول فيها الأصول **المتاحة** بس (نشطة وغير مدرجة في مشروع)، وقائمة المشروعات فيها المشروعات **النشطة** بس (Gulf Petro Warehouse المعطّل مش موجود). أي أصل بيتدرج بيختفي من القائمة بعدها ومايتدرجش في مشروعين.',
  },
  'week-5-facilities#8': {
    expected:
      'عنوان الصفحة "تفاصيل المنشأة الوسيطة". بتعرض: اللوجو، الاسم والحالة، الاسم المختصر، الخدمة البيئية ونسبة الكفاءة، العنوان، رابط الخريطة، بيانات العقد وزر تحميل المرفق، البيانات الإضافية، "آخر تحديث: تم بواسطة ... – التاريخ". قسم "الخدمات الفرعية المدعومة" فيه الخدمات الفرعية اللي مختارة المنشأة دي (كل واحدة رابط لتفاصيلها)، أو رسالة إن مفيش لو مفيش.',
  },
  'week-5-services#2': {
    expected:
      'أعمدة الجدول: اسم الخدمة الأساسية (رابط لتفاصيلها) / نوعية الخدمة / الخدمات الفرعية (كل واحدة رابط لتفاصيلها) / الحالة / التفاصيل (عين + قلم). البحث بـ`sludge` يطلّع "Hazardous Waste Transport" لأن البحث بيشمل أسماء الخدمات الفرعية الظاهرة في الجدول. فلترا النوعية والحالة وترقيم الصفحات وترتيب الأعمدة شغالين.',
  },
  'week-5-services#4': {
    stepsList: [
      'من القائمة الجانبية افتح Services ← Main Services ← Add.',
      'دوس Submit والفورم فاضي.',
      'اكتب اسم، اختار "نفايات خطرة"، وعلّم تصنيفين مركبات.',
      'دوس Submit.',
    ],
  },
  'week-5-services#6': {
    expected:
      'عنوان الصفحة "تعديل تفاصيل خدمة أساسية". نوعية الخدمة وتصنيفات المركبات الداعمة ظاهرين ومقفولين مع توضيح إن الاسم والبيانات الإضافية بس قابلين للتعديل. الاسم والبيانات الإضافية بيتحفظوا وترجع للتفاصيل برسالة نجاح. زر "Cancel" بيرجّع للتفاصيل من غير حفظ.',
  },
  'week-5-services#7': {
    expected:
      'الكارت بينبّه إن التعطيل بيعطّل كل الخدمات الفرعية. بعد التعطيل: الخدمة الأساسية معطّلة والخدمتين الفرعيتين تحتها معطّلين كمان. إعادة تنشيط الأساسية بترجّع الخدمتين الفرعيتين نشطين تاني تلقائيًا (أي فرعية كانت معطّلة لوحدها قبل كده بتفضل معطّلة). الخدمة الأساسية المعطّلة مابتظهرش في قائمة الاختيار عند إنشاء خدمة فرعية.',
  },
  'week-5-services#13': {
    expected:
      'عنوان الصفحة "تعديل تفاصيل خدمة". الفورم متعبّي بالبيانات الحالية، والخدمة الأساسية والنوع والسعات والمنشآت وإيصال التدوير ظاهرين ومقفولين (الاسم والكود اللوني والبيانات الإضافية بس قابلين للتعديل). بعد الحفظ بترجع للتفاصيل برسالة نجاح.',
  },
  'week-5-services#5': {
    expected:
      'عنوان الصفحة "تفاصيل خدمة أساسية" وتحته "آخر تحديث: تم بواسطة ... – التاريخ". الكارت الشمال: الاسم، الحالة، نوعية الخدمة، عدد الخدمات الفرعية، التصنيفات الداعمة، البيانات الإضافية، وزرا Edit وBack. اليمين: **الإحصائيات** (إجمالي الرحلات، المشاريع التي طلبت الخدمة، السائقين، الكميات بالطن والمتر المكعب، الموارد: مركبات/حاويات/صهاريج)، وتحتها جدول **"الخدمات الفرعية التابعة لها"** (بحث + ترقيم + فلتر نوع الأصل وفلتر الحالة + زر **Export** Excel/PDF بيصدّر نفس الصفوف المعروضة، كل اسم رابط) وزر "Create New Sub-service" بيفتح الفورم والخدمة الأساسية مختارة.',
  },
  'week-5-services#15': {
    frd: '§1.8 (الخدمات الفرعية المدعومة تُحدَّد من مدخلات الخدمات الفرعية)',
    expected:
      'قسم "الخدمات الفرعية المدعومة" فيه "Oily sludge (Hazardous Waste Transport)" كرابط لتفاصيلها. في صفحة التعديل الخدمة البيئية والكفاءة مقفولين برسالة إن المنشأة مستخدمة، والاسم وباقي البيانات قابلين للتعديل.',
  },
  'week-1-2-auth-users-roles#8': {
    title: 'إنشاء مستخدم GCM (مدخل بيانات / مراقب فقط)',
    stepsList: [
      'من القائمة الجانبية افتح Users.',
      'دوس "Add User".',
      'اختار GCM Staff.',
      'املأ الحقول واختر أي دور من الاتنين المتاحين.',
      'دوس حفظ.',
    ],
    expected:
      'المستخدم يظهر فورًا في قائمة Users. **خيارات الدور في فورم "Add User" ده تحديدًا** فيها دورين بس (مدخل بيانات ومراقب) — دور مدير النظام ودور السائق مش من ضمنهم في **الفورم ده**، لأن السائق ليه صفحة إنشاء مخصصة منفصلة (Drivers، وبعدين Add Driver). ده مايمنعش إن حسابات السائقين الموجودة أصلًا تظهر عادي في قائمة Users العامة مع باقي الحسابات.',
  },
  'week-1-2-auth-users-roles#22': {
    title: 'توقيع وختم موظفي GCM (اختياري)',
    frd: 'طلب العميل — نفس صورتي التوقيع والختم التشغيلي لحسابات العملاء',
    stepsList: [
      'من القائمة الجانبية افتح Users ← "Add User" ← GCM Staff.',
      'املأ البيانات وارفع صورة توقيع وصورة ختم (PNG أو JPG حتى 2 ميجا) ودوس حفظ.',
      'افتح تفاصيل الحساب الجديد، ثم دوس Edit وارفع صورة توقيع جديدة فقط (من غير ما تلمس الختم) واحفظ.',
      'أنشئ حساب GCM تاني من غير ما ترفع أي صورة.',
      'افتح My Profile بتاعك (من قائمة الحساب أعلى الصفحة) وشوف كارت "Signature & Stamp".',
    ],
    expected:
      'الصورتين اختياريتين — الحساب بيتنشئ من غيرهم. بيظهروا في صفحة التفاصيل، وفي التعديل بيظهر رابط "Current file" جنب كل واحدة، ورفع التوقيع لوحده بيسيب الختم زي ما هو. الملف اللي مش صورة أو الأكبر من 2 ميجا بيترفض. في My Profile بتاعك فيه كارت "Signature & Stamp" تقدر تغيّر منه صورتك بنفسك.',
  },
  'week-1-2-auth-users-roles#17': {
    title: 'عناصر القائمة الجانبية لمدير النظام',
    stepsList: ['سجّل دخول بحسابك (مدير النظام) وبصّ على القائمة الجانبية.', 'بدّل اللغة لعربي.'],
    expected:
      'تشوف Dashboard، وتحت عنوان **Accounts**: Users وDrivers، وتحت **Fleet & Assets**: Vehicles (List/Add/Categories) وAssets (List/Add/Capacities)، وتحت **Operations**: Facilities وServices (Main/Sub) وContractors، وتحت **Clients & Projects**: Client Companies وClient Projects، وتحت **System**: General Settings — من غير أي بنود تانية. بالعربي العناوين بتبقى "الحسابات" و"الأسطول والأصول" و"العمليات" و"العملاء والمشروعات" و"النظام".',
  },
  'week-3-frd-gap-review#1': {
    steps: 'سجّل دخول بحسابك. بصّ على الشريط العلوي. بدّل اللغة لعربي وارجع لإنجليزي.',
    expected:
      'التاريخ والوقت الحاليين ظاهرين على **الطرف المقابل لمجموعة الأيقونات** (في الإنجليزي: التاريخ شمال والأيقونات يمين، وفي العربي بالعكس) وبيتحدّثوا لوحدهم (كل نص دقيقة تقريبًا). في العربي الأرقام عربية (مثلاً `١٤/٠٩/٢٠٢٦، ٤:٢٢ م`).',
  },
  'week-3-frd-gap-review#4': {
    stepsList: [
      'افتح My Profile.',
      'حاول تكتب في خانة الاسم.',
      'دوس "Upload new photo" واختار صورة PNG/JPG صغيرة، ولاحظ المعاينة.',
      'دوس "Save photo".',
      'جرّب ملف مش صورة، وجرّب "Save photo" من غير ما تختار صورة.',
    ],
    expected:
      'الاسم والبريد للقراءة فقط. الصفحة كارتين: كارت الصورة وتحته كارت "Change Password" (مفيش تابات)، ومعاهم كارت "Signature & Stamp" لموظفي GCM. الصورة بتتحفظ وتظهر في الصفحة **وفي الشريط العلوي**. لو اخترت ملف مش صورة، أو دُست حفظ من غير ما تختار حاجة، هتظهر رسالة خطأ واضحة.',
  },
  'week-3-frd-gap-review#6': {
    steps: 'افتح فورم إنشاء مستخدم، فورم إنشاء سائق، وصفحة تعديل/تفاصيل كل واحد، بالعربي والإنجليزي.',
    expected:
      'التسمية بالظبط "رقم الموبايل (حساب واتساب)" في العربي و"Mobile No. (with WhatsApp)" في الإنجليزي — في كل الأماكن دي.',
  },
  'week-3-frd-gap-review#10': {
    steps: 'بصّ على "Vehicles" في القائمة الجانبية.',
    expected: '"Vehicles" فيها بندين **List** و**Categories**.',
  },
  'week-3-frd-gap-review#12': {
    stepsList: [
      'افتح Vehicles، وبعدين List، وعدّ الكروت في "Vehicles by category".',
      'أضف تصنيف جديد زي البند اللي فات، وارجع لصفحة المركبات.',
      'جرّب فلتر "All categories"، وفورم إضافة مركبة، وفورم إضافة سائق، وفورم إضافة أصل.',
    ],
  },
  'week-3-frd-gap-review#14': {
    expected:
      '(أ) التصنيف بيتمسح والكارت بتاعه بيختفي من صفحة المركبات. (ب) أيقونة الحذف **مقفولة** وعليها تلميح "In use — can\'t be deleted". (ج) أيقونة الحذف **مقفولة دايمًا** على الستة الأساسيين وعليها تلميح "Primary category — can\'t be deleted" (الاسم بس هو اللي بيتعدّل).',
  },
  'week-3-frd-gap-review#23': {
    stepsList: [
      'في أي قائمة (Users / Drivers / Vehicles / Vehicle Categories / Assets / Asset Categories) دوس زرار Export واختار Pdf.',
      'جرّب كمان Excel.',
    ],
  },
  'week-3-vehicles-drivers-assets#1': {
    title: 'عرض قائمة المركبات',
    steps: 'افتح قائمة المركبات.',
    expected: 'تشوف القائمة والكروت والفلاتر.',
  },
  'week-3-vehicles-drivers-assets#2': {
    title: 'إنشاء مركبة جديدة',
    stepsList: [
      'افتح Add Vehicle.',
      'املأ (لوحة + فئة + تبعية + وثائق بأرقام وتواريخ + تصريح دخول).',
      'احفظ.',
    ],
    expected: 'المركبة تتنشئ بنجاح وتظهر فورًا في القائمة.',
  },
  'week-3-vehicles-drivers-assets#4': {
    title: 'اللوحة فريدة داخل الشركة',
    expected: 'المحاولة التانية بترفض برسالة إن اللوحة مستخدمة بالفعل.',
  },
  'week-3-vehicles-drivers-assets#6': {
    title: 'تغيير حالة مركبة (الصيانة / تعطيل / إعادة تنشيط)',
    steps: 'حوّل مركبة لـ"في الصيانة"، ثم لـ"معطّل"، ثم أعِد تنشيطها من "معطّل".',
    expected: 'التحويلات التلاتة بتنجح.',
  },
  'week-3-vehicles-drivers-assets#10': {
    title: 'الوصول لقائمة السائقين',
    steps: 'افتح قائمة السائقين.',
    expected:
      'تشوف "Drivers" وتقدر تنشئ وتعدّل. زر الإنشاء اسمه "إنشاء مستخدم جديد سائق" ويوديك لصفحة السائق المخصصة (مش فورم المستخدمين).',
  },
  'week-3-vehicles-drivers-assets#16': {
    title: 'عرض قائمة الأصول',
    frd: '§1.7.3 (الإنشاء والتعديل مسؤولية مدير النظام أو مدخل البيانات؛ المراقب عرض فقط)',
    steps: 'افتح قائمة الأصول.',
    expected: 'تشوف "Assets" (List + Capacities).',
  },
};

function buildClientDocs(sourceDocs) {
  const byId = new Map();
  for (const d of sourceDocs) {
    for (const s of d.sections) {
      for (const it of s.items) byId.set(it.id, it);
    }
  }

  let n = 0;
  const sections = CLIENT_GROUPS.map(group => {
    const items = group.ids.map(id => {
      const it = byId.get(id);
      if (!it) throw new Error(`CLIENT_GROUPS references an id that no longer exists in any week-*.md file: ${id}`);
      const ov = CLIENT_OVERRIDES[id] || {};
      n += 1;
      const fields = it.fields
        .filter(f => f.key !== 'يغطيه آليًا')
        .map(f => {
          if (f.key === 'FRD' && ov.frd) return { ...f, text: ov.frd, subs: [] };
          if (f.key === 'الخطوات' && ov.stepsList) return { ...f, text: '', subs: ov.stepsList };
          if (f.key === 'الخطوات' && ov.steps) return { ...f, text: ov.steps, subs: [] };
          if (f.key === 'المتوقع' && ov.expectedList) return { ...f, text: '', subs: ov.expectedList };
          if (f.key === 'المتوقع' && ov.expected) return { ...f, text: ov.expected, subs: [] };
          return f;
        });
      return { ...it, num: String(n), title: ov.title || it.title, fields };
    });
    return { title: group.title, items };
  });

  // One unified document (no per-source-file titles/sync lines) — the page's
  // own <h1> is the only title; an empty doc.title tells renderDocs to skip
  // the (otherwise redundant) per-doc heading.
  return [{ key: 'client', title: '', sync: '', sections }];
}

const clientDocs = buildClientDocs(docs);
const clientTotal = clientDocs.reduce((n, d) => n + d.sections.reduce((m, s) => m + s.items.length, 0), 0);
const clientBody = renderDocs(clientDocs);

// One page shell for client-checklist.html and every per-role UAT file (build-uat.mjs): only the
// header/credentials block, the body, the counter and the storage/export names differ.
const renderClientPage = ({ title, h1, sub, setup, body, total, storageKey, xlsxName }) => `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
<style>
  :root {
    --bg:#f6f7fb; --card:#fff; --text:#1f2430; --muted:#69708a; --line:#e3e6ef;
    --accent:#4f46e5; --ok:#15803d; --ok-bg:#e6f6ec; --bad:#b91c1c; --bad-bg:#fdeaea; --code:#eef0f8;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#14161f; --card:#1d2030; --text:#e7e9f3; --muted:#9aa1bd; --line:#2d3148;
      --accent:#8b85ff; --ok:#4ade80; --ok-bg:#15301f; --bad:#f87171; --bad-bg:#3a1c1c; --code:#272b40; }
  }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); line-height:1.7;
    font-family:"Segoe UI",Tahoma,"Noto Naskh Arabic",Arial,sans-serif; }
  code { background:var(--code); padding:1px 6px; border-radius:5px; font-size:.88em;
    direction:ltr; unicode-bidi:isolate; font-family:Consolas,monospace; overflow-wrap:anywhere; word-break:break-word; }
  dt, dd, .row, h3 { min-width:0; overflow-wrap:anywhere; }
  .wrap { max-width:960px; margin:0 auto; padding:0 16px 80px; }
  h1 { font-size:1.5rem; margin:28px 0 4px; }
  .sub { color:var(--muted); margin:0 0 20px; }

  .setup { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 18px; margin-bottom:18px; }
  .setup h2 { margin:0 0 8px; font-size:1.05rem; }
  .setup table { width:100%; border-collapse:collapse; margin-top:4px; font-size:.95rem; }
  .setup th, .setup td { text-align:start; padding:6px 8px; border-bottom:1px solid var(--line); }
  .setup a { color:var(--accent); font-weight:600; }
  .setup p { margin:6px 0; }
  .setup ul { margin:6px 0; padding-inline-start:22px; }
  .setup h2 + p, .setup table + h2, .setup ul + h2, .setup p + h2 { margin-top:14px; }
  .doc-note { color:var(--muted); margin:2px 0 8px; font-size:.92rem; }

  .bar { background:var(--bg); padding:12px 0 10px; border-bottom:1px solid var(--line); }
  @media (min-width:720px) { .bar { position:sticky; top:0; z-index:5; } }
  .stats { display:flex; gap:14px; flex-wrap:wrap; align-items:center; font-size:.95rem; }
  .stats b { font-size:1.1rem; }
  .prog { height:9px; background:var(--line); border-radius:9px; overflow:hidden; margin:8px 0; display:flex; }
  .prog i { display:block; height:100%; }
  .prog .p { background:var(--ok); } .prog .f { background:var(--bad); }
  .tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .tools input[type=search], .tools input[type=text] { padding:6px 10px; border:1px solid var(--line); border-radius:8px;
    background:var(--card); color:var(--text); font:inherit; min-width:150px; }
  .chip, .btn { border:1px solid var(--line); background:var(--card); color:var(--text); padding:5px 12px;
    border-radius:20px; cursor:pointer; font:inherit; font-size:.9rem; }
  .chip.on { background:var(--accent); border-color:var(--accent); color:#fff; }
  .btn:hover, .chip:hover { border-color:var(--accent); }

  .doc-title { font-size:1.15rem; margin:30px 0 6px; padding-bottom:6px; border-bottom:2px solid var(--accent); }
  .group { margin:12px 0; }
  .group > summary { cursor:pointer; font-weight:700; padding:8px 4px; }
  .count { font-weight:400; color:var(--muted); font-size:.85rem; }

  .item { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px 16px; margin:10px 0;
    border-inline-start:5px solid var(--line); }
  .item.is-pass { border-inline-start-color:var(--ok); background:linear-gradient(var(--ok-bg),var(--ok-bg)) ; }
  .item.is-fail { border-inline-start-color:var(--bad); background:linear-gradient(var(--bad-bg),var(--bad-bg)); }
  .item header { display:flex; align-items:center; gap:10px; }
  .item h3 { font-size:1rem; margin:0; flex:1; }
  .check { display:flex; align-items:center; gap:8px; cursor:pointer; }
  .check input { width:22px; height:22px; accent-color:var(--ok); cursor:pointer; }
  .num { background:var(--code); border-radius:8px; padding:0 8px; font-weight:700; font-size:.85rem; direction:ltr; }
  .failbtn { border:1px solid var(--bad); color:var(--bad); background:transparent; border-radius:8px; padding:3px 12px; cursor:pointer; font:inherit; font-size:.85rem; }
  .item.is-fail .failbtn { background:var(--bad); color:#fff; }
  dl { margin:10px 0 6px; }
  .row { display:grid; grid-template-columns:130px 1fr; gap:6px; padding:5px 0; border-top:1px dashed var(--line); }
  dt { color:var(--muted); font-size:.85rem; padding-top:2px; }
  dd { margin:0; } dd ul { margin:4px 0 0; padding-inline-start:20px; }
  .note { width:100%; resize:vertical; border:1px solid var(--line); border-radius:8px; background:var(--bg);
    color:var(--text); padding:6px 10px; font:inherit; font-size:.9rem; }
  .item.hidden, .group.hidden, .doc.hidden { display:none; }
  @media (max-width:640px) { .row { grid-template-columns:1fr; gap:0; } }
  @media print { .bar, .tools, .setup { display:none; } .item { break-inside:avoid; } body { background:#fff; color:#000; } }
</style>
<!-- Builds the real .xlsx the export button downloads. Plain SheetJS (the
     free "xlsx" package) silently drops cell styles on write — only its paid
     Pro build honors them — so this uses xlsx-js-style, a community fork
     with the identical API that actually writes fill/font colors, to get
     the pass=green/fail=red rows. Pinned version, jsdelivr (not on cdnjs). -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
</head>
<body>
<div class="wrap">
  <h1>${h1}</h1>
  <p class="sub">${sub}</p>

${setup}

  <div class="bar">
    <div class="stats">
      <span>تم: <b id="s-pass">0</b></span>
      <span>فشل: <b id="s-fail" style="color:var(--bad)">0</b></span>
      <span>متبقي: <b id="s-left">0</b></span>
      <span>من <b>${total}</b> بند</span>
    </div>
    <div class="prog"><i class="p" id="b-pass"></i><i class="f" id="b-fail"></i></div>
    <div class="tools">
      <input type="text" id="tester" placeholder="اسم المُختبِر">
      <button type="button" class="chip on" data-f="all">الكل</button>
      <button type="button" class="chip" data-f="left">المتبقي</button>
      <button type="button" class="chip" data-f="pass">تم</button>
      <button type="button" class="chip" data-f="fail">فشل</button>
      <input type="search" id="q" placeholder="بحث…">
      <button type="button" class="btn" id="export">تصدير Excel</button>
      <button type="button" class="btn" id="reset">مسح كل العلامات</button>
    </div>
  </div>
${body}
</div>

<script>
(function () {
  var KEY = '${storageKey}';
  var state = {};
  try { state = JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) {}
  state.items = state.items || {};
  var items = [].slice.call(document.querySelectorAll('.item'));
  var filter = 'all';

  function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
  function get(id) { return state.items[id] || (state.items[id] = { s: '', n: '' }); }

  function paint(el) {
    var st = get(el.dataset.id);
    el.classList.toggle('is-pass', st.s === 'pass');
    el.classList.toggle('is-fail', st.s === 'fail');
    el.querySelector('.pass').checked = st.s === 'pass';
    el.querySelector('.note').value = st.n || '';
  }

  function refresh() {
    var p = 0, f = 0, q = document.getElementById('q').value.trim().toLowerCase();
    items.forEach(function (el) {
      var s = get(el.dataset.id).s;
      if (s === 'pass') p++; else if (s === 'fail') f++;
      var show = filter === 'all' || (filter === 'left' && !s) || filter === s;
      if (show && q) show = el.textContent.toLowerCase().indexOf(q) !== -1;
      el.classList.toggle('hidden', !show);
    });
    document.querySelectorAll('.group').forEach(function (g) {
      var its = [].slice.call(g.querySelectorAll('.item'));
      var done = its.filter(function (i) { return get(i.dataset.id).s === 'pass'; }).length;
      g.querySelector('.count').textContent = '(' + done + '/' + its.length + ')';
      g.classList.toggle('hidden', its.every(function (i) { return i.classList.contains('hidden'); }));
    });
    document.querySelectorAll('.doc').forEach(function (d) {
      d.classList.toggle('hidden', [].every.call(d.querySelectorAll('.group'), function (g) { return g.classList.contains('hidden'); }));
    });
    var n = items.length;
    document.getElementById('s-pass').textContent = p;
    document.getElementById('s-fail').textContent = f;
    document.getElementById('s-left').textContent = n - p - f;
    document.getElementById('b-pass').style.width = (p / n * 100) + '%';
    document.getElementById('b-fail').style.width = (f / n * 100) + '%';
  }

  items.forEach(function (el) {
    paint(el);
    el.querySelector('.pass').addEventListener('change', function (e) {
      get(el.dataset.id).s = e.target.checked ? 'pass' : ''; save(); paint(el); refresh();
    });
    el.querySelector('.failbtn').addEventListener('click', function () {
      var st = get(el.dataset.id); st.s = st.s === 'fail' ? '' : 'fail'; save(); paint(el); refresh();
    });
    el.querySelector('.note').addEventListener('input', function (e) {
      get(el.dataset.id).n = e.target.value; save();
    });
  });

  document.querySelectorAll('.chip').forEach(function (c) {
    c.addEventListener('click', function () {
      document.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('on'); });
      c.classList.add('on'); filter = c.dataset.f; refresh();
    });
  });
  document.getElementById('q').addEventListener('input', refresh);

  var tester = document.getElementById('tester');
  tester.value = state.tester || '';
  tester.addEventListener('input', function () { state.tester = tester.value; save(); });

  document.getElementById('reset').addEventListener('click', function () {
    if (!confirm('هيتمسح كل التعليم والملاحظات. متأكد؟')) return;
    state.items = {}; save(); items.forEach(paint); refresh();
  });

  // Builds a real .xlsx (via SheetJS, loaded above) from the same DOM the
  // checklist already renders — needs the one-time CDN fetch (cached by the
  // browser after that), but the tester already needs internet to reach the
  // login link above, so that's not an extra requirement in practice.
  document.getElementById('export').addEventListener('click', function () {
    if (typeof XLSX === 'undefined') {
      alert('تعذّر تحميل مكتبة تصدير Excel — تأكد إن عندك اتصال بالإنترنت وحاول تاني.');
      return;
    }

    var STATUS_LABEL = { pass: 'تم', fail: 'فشل' };
    // Same colors as the page's own pass/fail cards (var(--ok)/var(--ok-bg),
    // var(--bad)/var(--bad-bg) — light theme values, since Excel has no
    // dark-mode concept to follow).
    var ROW_STYLE = {
      pass: { fill: { fgColor: { rgb: 'E6F6EC' } }, font: { color: { rgb: '15803D' } } },
      fail: { fill: { fgColor: { rgb: 'FDEAEA' } }, font: { color: { rgb: 'B91C1C' } } },
    };
    var HEADER_STYLE = { fill: { fgColor: { rgb: 'E3E6EF' } }, font: { bold: true } };

    var rows = [['القسم', 'رقم', 'البند', 'الحالة', 'ملاحظات']];
    var statuses = [null]; // no color for the header row
    document.querySelectorAll('.group').forEach(function (groupEl) {
      // "القسم" is the module name (Users, Vehicles, ...), not a leftover
      // count badge — grab the summary's own first text node only.
      var groupTitle = groupEl.querySelector('summary').childNodes[0].textContent.trim();
      // Per-role UAT pages split the items into parts (phase 2 / phase 1 gaps / improvements) — keep
      // the part in the sheet's first column too. The plain client page has no part heading.
      var partEl = groupEl.closest('.doc').querySelector('.doc-title');
      if (partEl) groupTitle = partEl.childNodes[0].textContent.trim() + ' — ' + groupTitle;
      [].forEach.call(groupEl.querySelectorAll('.item'), function (el) {
        var st = get(el.dataset.id);
        var num = el.querySelector('.num').textContent;
        rows.push([groupTitle, num, el.dataset.title, STATUS_LABEL[st.s] || 'لم يتم بعد', st.n || '']);
        statuses.push(st.s || null);
      });
    });

    var sheet = XLSX.utils.aoa_to_sheet(rows);
    sheet['!cols'] = [{ wch: 45 }, { wch: 6 }, { wch: 50 }, { wch: 12 }, { wch: 40 }];
    rows.forEach(function (row, r) {
      var rowStyle = r === 0 ? HEADER_STYLE : ROW_STYLE[statuses[r]];
      if (!rowStyle) return;
      row.forEach(function (_, c) {
        var ref = XLSX.utils.encode_cell({ r: r, c: c });
        if (sheet[ref]) sheet[ref].s = rowStyle;
      });
    });

    var workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'نتيجة الاختبار');
    XLSX.writeFile(workbook, '${xlsxName}');
  });

  refresh();
})();
</script>
</body>
</html>
`;

const clientHtml = renderClientPage({
  title: 'GCM Portal — اختبار القبول',
  h1: 'GCM Portal — اختبار القبول',
  sub: 'لكل بند: جرّب الخطوات وقارن بالنتيجة المتوقعة. علّم ✔ لو نجح، ودوس "فشل" واكتب ملاحظة لو طلع غلط. تقدّمك بيتحفظ تلقائيًا في المتصفح ده، ولما تخلص صدّر النتيجة.',
  setup: `  <div class="setup">
    <h2>بيانات الدخول</h2>
    <table>
      <tr><th>الرابط</th><td><a href="https://gcm-dev.digitswat.com" target="_blank" rel="noopener">https://gcm-dev.digitswat.com</a></td></tr>
      <tr><th>البريد الإلكتروني</th><td><code>admin@gcm.test</code></td></tr>
      <tr><th>كلمة المرور</th><td><code>password</code></td></tr>
    </table>
  </div>`,
  body: clientBody,
  total: clientTotal,
  storageKey: 'gcm-client-checklist-v1',
  xlsxName: 'نتيجة اختبار GCM Portal.xlsx',
});

writeFileSync(join(dir, 'client-checklist.html'), clientHtml);
console.log(`client-checklist.html built — ${clientTotal} items (curated System-Admin-only subset for the client's tester)`);

// Per-role UAT files (docs/acceptance/uat/*.html) — phase 2 + phase 1 gaps + improvements, one file per role.
buildUat({ dir, docs, parseFile, renderDocs, renderClientPage, esc, inline, CLIENT_OVERRIDES });
