// Builds docs/acceptance/checklist.html from every week-*.md acceptance file.
// Run from the project root:  node docs/acceptance/build-checklist.mjs
// The .md files stay the single source of truth — never edit checklist.html by hand.

import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

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
    <h2 class="doc-title">${esc(d.title)}${d.sync ? `<small>${esc(d.sync)}</small>` : ''}</h2>
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

// A section heading occasionally narrates internal project context (e.g. "an
// out-of-FRD client request", "notes from testing on the server") that reads
// like a dev note rather than an acceptance-test section — renamed here.
const CLIENT_SECTION_TITLES = {
  'د) تصنيفات المركبات القابلة للإدارة (طلب العميل — خارج الـFRD)': 'د) تصنيفات المركبات',
  'هـ) الأداء وإحساس المستخدم (ملاحظات من التجربة على السيرفر)': 'هـ) الأداء وتجربة الاستخدام',
};

const CLIENT_DOC_TITLES = {
  'week-1-2-auth-users-roles': 'تسجيل الدخول وإدارة المستخدمين والأدوار',
  'week-3-frd-gap-review': 'الشريط العلوي، الملف الشخصي، وتصنيفات المركبات',
  'week-3-vehicles-drivers-assets': 'المركبات والسائقين والأصول',
};

const CLIENT_INCLUDE = new Set(
  [
    // '14' excluded on purpose — it's the only remaining item that even
    // acknowledges the Super Admin/platform layer exists (redirected away
    // from `/platform/roles`), and that layer must stay invisible to the
    // client entirely, not just inaccessible.
    ['week-1-2-auth-users-roles', ['1', '2', '8', '10', '11', '12', '13', '17', '18']],
    [
      'week-3-frd-gap-review',
      ['1', '2', '4', '5', '6', '7', '9', '10', '11', '12', '13', '14', '17', '18', '19', '20', '21', '22', '23', '24', '25'],
    ],
    [
      'week-3-vehicles-drivers-assets',
      ['1', '2', '3', '4', '5', '6', '8', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22'],
    ],
  ].flatMap(([key, nums]) => nums.map(n => `${key}#${n}`)),
);

const CLIENT_OVERRIDES = {
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
  'week-1-2-auth-users-roles#17': {
    title: 'عناصر القائمة الجانبية لمدير النظام',
    steps: 'سجّل دخول بحسابك (مدير النظام) وبصّ على القائمة الجانبية.',
    expected:
      'تشوف Dashboard، وتحت عنوان **Accounts**: Users وDrivers، وتحت عنوان **Fleet & Assets**: Vehicles وAssets — من غير أي بنود تانية.',
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
      'الاسم والبريد للقراءة فقط، ومفيش تابات "Account/Security". الصورة بتتحفظ وتظهر في الصفحة **وفي الشريط العلوي**. لو اخترت ملف مش صورة، أو دُست حفظ من غير ما تختار حاجة، هتظهر رسالة خطأ واضحة.',
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
  'week-3-frd-gap-review#18': {
    steps: 'سجّل دخول وبصّ على القائمة الجانبية. بدّل اللغة لعربي.',
    expected:
      'تشوف Dashboard ثم عنوان **Accounts** (تحته Users وDrivers) ثم عنوان **Fleet & Assets** (تحته Vehicles وAssets). بالعربي: "الحسابات" و"الأسطول والأصول".',
  },
  'week-3-frd-gap-review#23': {
    stepsList: [
      'في أي قائمة (Users / Drivers / Vehicles / Vehicle Categories / Assets / Asset Categories) دوس زرار Export واختار Pdf.',
      'جرّب كمان Excel.',
    ],
  },
  'week-3-vehicles-drivers-assets#1': {
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
    title: 'حالة المركبة',
    steps: 'حوّل مركبة لـ"في الصيانة"، ثم لـ"معطّل"، ثم أعِد تنشيطها من "معطّل".',
    expected: 'التحويلات التلاتة بتنجح.',
  },
  'week-3-vehicles-drivers-assets#10': {
    steps: 'افتح قائمة السائقين.',
    expected:
      'تشوف "Drivers" وتقدر تنشئ وتعدّل. زر الإنشاء اسمه "إنشاء مستخدم جديد سائق" ويوديك لصفحة السائق المخصصة (مش فورم المستخدمين).',
  },
  'week-3-vehicles-drivers-assets#16': {
    frd: '§1.7.3 (الإنشاء والتعديل مسؤولية مدير النظام أو مدخل البيانات؛ المراقب عرض فقط)',
    steps: 'افتح قائمة الأصول.',
    expected: 'تشوف "Assets" (List + Categories).',
  },
};

function buildClientDocs(sourceDocs) {
  return sourceDocs
    .map(d => {
      let n = 0;
      const sections = d.sections
        .map(s => {
          const items = s.items
            .filter(it => CLIENT_INCLUDE.has(it.id))
            .map(it => {
              const ov = CLIENT_OVERRIDES[it.id] || {};
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
          return { ...s, title: CLIENT_SECTION_TITLES[s.title] || s.title, items };
        })
        .filter(s => s.items.length > 0);
      return { key: d.key, title: CLIENT_DOC_TITLES[d.key] || d.title, sync: '', sections };
    })
    .filter(d => d.sections.length > 0);
}

const clientDocs = buildClientDocs(docs);
const clientTotal = clientDocs.reduce((n, d) => n + d.sections.reduce((m, s) => m + s.items.length, 0), 0);
const clientBody = renderDocs(clientDocs);

const clientHtml = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GCM Portal — اختبار القبول</title>
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
<!-- Builds the real .xlsx the export button downloads (SheetJS, pinned version) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div class="wrap">
  <h1>GCM Portal — اختبار القبول</h1>
  <p class="sub">لكل بند: جرّب الخطوات وقارن بالنتيجة المتوقعة. علّم ✔ لو نجح، ودوس "فشل" واكتب ملاحظة لو طلع غلط. تقدّمك بيتحفظ تلقائيًا في المتصفح ده، ولما تخلص صدّر النتيجة.</p>

  <div class="setup">
    <h2>بيانات الدخول</h2>
    <table>
      <tr><th>الرابط</th><td><a href="https://gcm-dev.digitswat.com" target="_blank" rel="noopener">https://gcm-dev.digitswat.com</a></td></tr>
      <tr><th>البريد الإلكتروني</th><td><code>admin@gcm.test</code></td></tr>
      <tr><th>كلمة المرور</th><td><code>password</code></td></tr>
    </table>
  </div>

  <div class="bar">
    <div class="stats">
      <span>تم: <b id="s-pass">0</b></span>
      <span>فشل: <b id="s-fail" style="color:var(--bad)">0</b></span>
      <span>متبقي: <b id="s-left">0</b></span>
      <span>من <b>${clientTotal}</b> بند</span>
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
${clientBody}
</div>

<script>
(function () {
  var KEY = 'gcm-client-checklist-v1';
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
    var rows = [['القسم', 'رقم', 'البند', 'الحالة', 'ملاحظات']];
    document.querySelectorAll('.doc').forEach(function (docEl) {
      var docTitle = docEl.querySelector('.doc-title').textContent.trim();
      [].forEach.call(docEl.querySelectorAll('.item'), function (el) {
        var st = get(el.dataset.id);
        var num = el.querySelector('.num').textContent;
        rows.push([docTitle, num, el.dataset.title, STATUS_LABEL[st.s] || 'لم يتم بعد', st.n || '']);
      });
    });

    var sheet = XLSX.utils.aoa_to_sheet(rows);
    sheet['!cols'] = [{ wch: 30 }, { wch: 6 }, { wch: 50 }, { wch: 12 }, { wch: 40 }];
    var workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'نتيجة الاختبار');
    XLSX.writeFile(workbook, 'نتيجة اختبار GCM Portal.xlsx');
  });

  refresh();
})();
</script>
</body>
</html>
`;

writeFileSync(join(dir, 'client-checklist.html'), clientHtml);
console.log(`client-checklist.html built — ${clientTotal} items (curated System-Admin-only subset for the client's tester)`);
