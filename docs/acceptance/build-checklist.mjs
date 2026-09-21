// Builds docs/acceptance/checklist.html from every week-*.md acceptance file.
// Run from the project root:  node docs/acceptance/build-checklist.mjs
// The .md files stay the single source of truth — never edit checklist.html by hand.

import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = dirname(fileURLToPath(import.meta.url));

const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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

const body = docs
  .map(
    d => `
  <section class="doc" data-doc="${esc(d.key)}">
    <h2 class="doc-title">${esc(d.title)}<small>${esc(d.sync)}</small></h2>
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
