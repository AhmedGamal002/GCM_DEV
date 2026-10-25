// Builds the per-role UAT files: docs/acceptance/uat/<role>.html (+ uat/index.html).
// Called at the end of build-checklist.mjs — run `node docs/acceptance/build-checklist.mjs`, never this file
// alone (it needs the parser/renderer/page shell that build-checklist.mjs owns).
//
// What goes in each file (and which wording is rewritten for which role) lives in uat-config.mjs. Items come
// from week-*.md (the shared source of truth) and uat-*.md (role walkthroughs). Like client-checklist.html,
// the output must not show that it was generated or point at internal material: no week/phase file names,
// no automated-test class names, no "FRD synced" lines. The scan at the bottom warns about the usual leaks.

import { mkdirSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { ACCOUNTS, OVERRIDES, PART_NOTES, PASSWORD, ROLES, ROLE_OVERRIDES, UAT_BASE_URL } from './uat-config.mjs';

const LEAK = [
  [/week-\d/i, 'اسم ملف أسبوع'],
  [/الأسبوع/, 'ذكر أسبوع'],
  [/\b\w+Test\b/, 'اسم كلاس تست'],
  [/::/, 'مرجع دالة/كلاس'],
  [/\.php\b|\.mjs\b|\.md\b/, 'اسم ملف داخلي'],
  [/آخر مزامنة/, 'سطر مزامنة FRD'],
  [/بند \d/, 'إشارة لرقم بند (الترقيم بيتغيّر من ملف لملف)'],
  [/\bseed\b/i, 'كلمة seed'],
];

export function buildUat({ dir, docs, parseFile, renderDocs, renderClientPage, esc, inline, CLIENT_OVERRIDES }) {
  const uatDocs = readdirSync(dir)
    .filter(f => /^uat-.*\.md$/.test(f))
    .sort()
    .map(parseFile);

  const byId = new Map();
  for (const d of [...docs, ...uatDocs]) {
    for (const s of d.sections) for (const it of s.items) byId.set(it.id, it);
  }

  const warnings = [];

  const applyOverride = (field, ov) => {
    if (field.key === 'FRD' && ov.frd) return { ...field, text: ov.frd, subs: [] };
    if (field.key === 'الخطوات' && ov.stepsList) return { ...field, text: '', subs: ov.stepsList };
    if (field.key === 'الخطوات' && ov.steps) return { ...field, text: ov.steps, subs: [] };
    if (field.key === 'المتوقع' && ov.expectedList) return { ...field, text: '', subs: ov.expectedList };
    if (field.key === 'المتوقع' && ov.expected) return { ...field, text: ov.expected, subs: [] };
    return field;
  };

  function buildRole(role) {
    const fixes = role.fixes || [];
    const fix = text => fixes.reduce((t, [re, to]) => t.replace(re, to), text);
    const seen = new Set();
    let n = 0;

    const parts = role.parts.map((part, i) => ({
      key: `${role.slug}-part-${i + 1}`,
      title: part.title,
      note: (role.partNotes || {})[part.title] || PART_NOTES[part.title],
      sync: '',
      sections: part.groups.map(group => {
        if (!group.ids.length) throw new Error(`${role.slug}: group "${group.title}" has no items`);
        const items = group.ids.map(id => {
          const it = byId.get(id);
          if (!it) throw new Error(`${role.slug}: "${group.title}" references an id that doesn't exist in any week-*.md / uat-*.md file: ${id}`);
          if (seen.has(id)) throw new Error(`${role.slug}: ${id} is listed twice`);
          seen.add(id);

          const ov = {
            ...(role.inheritClientOverrides ? CLIENT_OVERRIDES[id] : {}),
            ...OVERRIDES[id],
            ...(ROLE_OVERRIDES[role.slug] || {})[id],
          };
          n += 1;
          const fields = it.fields
            // "الدور" is the file's own role; "يغطيه آليًا" is internal (test class names).
            .filter(f => f.key !== 'يغطيه آليًا' && f.key !== 'الدور')
            .map(f => applyOverride(f, ov))
            .map(f => ({ ...f, text: fix(f.text), subs: f.subs.map(fix) }));
          const item = { ...it, num: String(n), title: fix(ov.title || it.title), fields };

          const text = [item.title, ...fields.flatMap(f => [f.text, ...f.subs])].join('\n');
          for (const [re, what] of LEAK) if (re.test(text)) warnings.push(`${role.slug} ${id}: ${what} (${text.match(re)[0]})`);
          return item;
        });
        return { title: group.title, items };
      }),
    }));

    return { parts, total: n };
  }

  const loginTable = (account, extra = '') => `<table>
      <tr><th>الرابط</th><td><a href="${UAT_BASE_URL}" target="_blank" rel="noopener">${UAT_BASE_URL}</a></td></tr>
      <tr><th>الدور</th><td>${esc(account.label)}</td></tr>
      <tr><th>البريد الإلكتروني</th><td><code>${esc(account.email)}</code></td></tr>
      <tr><th>كلمة المرور</th><td><code>${PASSWORD}</code></td></tr>${extra}
    </table>`;

  const setupHtml = role => {
    const own = ACCOUNTS[role.account];
    const others = (role.alsoNeeds || []).map(k => ACCOUNTS[k]);
    return `  <div class="setup">
    <h2>بيانات الدخول</h2>
    ${loginTable(own)}${
      others.length
        ? `
    <h2>حسابات تانية بتحتاجها في بنود معيّنة</h2>
    <table>
      ${others.map(a => `<tr><th>${esc(a.label)}</th><td><code>${esc(a.email)}</code> — كلمة المرور <code>${PASSWORD}</code></td></tr>`).join('\n      ')}
    </table>`
        : ''
    }${
      role.prereq?.length
        ? `
    <h2>قبل ما تبدأ</h2>
    <ul>
      ${role.prereq.map(p => `<li>${inline(p)}</li>`).join('\n      ')}
    </ul>`
        : ''
    }
  </div>`;
  };

  const built = ROLES.map(role => {
    const { parts, total } = buildRole(role);
    const html = renderClientPage({
      title: `GCM Portal — اختبار القبول — ${esc(role.label)}`,
      h1: `GCM Portal — اختبار القبول: ${esc(role.label)}`,
      sub:
        `${inline(role.intro)}<br>لكل بند: جرّب الخطوات وقارن بالنتيجة المتوقعة. علّم ✔ لو نجح، ودوس "فشل" واكتب ملاحظة لو طلع غلط. تقدّمك بيتحفظ تلقائيًا في المتصفح ده، ولما تخلص صدّر النتيجة.`,
      setup: setupHtml(role),
      body: renderDocs(parts),
      total,
      storageKey: `gcm-uat-${role.slug}-v1`,
      xlsxName: `نتيجة اختبار GCM Portal - ${role.label}.xlsx`,
    });
    return { role, total, html, partCounts: parts.map(p => ({ title: p.title, count: p.sections.reduce((m, s) => m + s.items.length, 0) })) };
  });

  const outDir = join(dir, 'uat');
  mkdirSync(outDir, { recursive: true });
  for (const b of built) writeFileSync(join(outDir, `${b.role.slug}.html`), b.html);
  writeFileSync(join(outDir, 'index.html'), indexPage(built, { esc }));

  for (const b of built) console.log(`uat/${b.role.slug}.html built — ${b.total} items`);
  if (warnings.length) {
    console.warn(`\nUAT leak warnings (${warnings.length}) — fix the wording through OVERRIDES in uat-config.mjs:`);
    for (const w of warnings) console.warn('  - ' + w);
  }
}

function indexPage(built, { esc }) {
  const rows = built
    .map(
      ({ role, total, partCounts }) => `
      <tr>
        <td><a href="${esc(role.slug)}.html"><strong>${esc(role.label)}</strong></a></td>
        <td><code>${esc(ACCOUNTS[role.account].email)}</code></td>
        <td class="num">${total}</td>
        <td class="parts">${partCounts.map(p => `<span>${esc(p.title.split(' — ')[0])}: <b>${p.count}</b></span>`).join('')}</td>
      </tr>`,
    )
    .join('');

  return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GCM Portal — اختبار القبول حسب الدور</title>
<style>
  :root { --bg:#f6f7fb; --card:#fff; --text:#1f2430; --muted:#69708a; --line:#e3e6ef; --accent:#4f46e5; --code:#eef0f8; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#14161f; --card:#1d2030; --text:#e7e9f3; --muted:#9aa1bd; --line:#2d3148; --accent:#8b85ff; --code:#272b40; }
  }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); line-height:1.7; font-family:"Segoe UI",Tahoma,"Noto Naskh Arabic",Arial,sans-serif; }
  .wrap { max-width:960px; margin:0 auto; padding:0 16px 60px; }
  h1 { font-size:1.5rem; margin:28px 0 4px; }
  h2 { font-size:1.1rem; margin:26px 0 8px; }
  p, li { color:var(--muted); }
  code { background:var(--code); padding:1px 6px; border-radius:5px; font-size:.88em; direction:ltr; unicode-bidi:isolate; font-family:Consolas,monospace; }
  a { color:var(--accent); }
  table { width:100%; border-collapse:collapse; background:var(--card); border:1px solid var(--line); border-radius:12px; overflow:hidden; }
  th, td { text-align:start; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-size:.85rem; font-weight:600; }
  td.num { font-weight:700; text-align:center; }
  .parts span { display:inline-block; margin-inline-end:12px; font-size:.85rem; color:var(--muted); white-space:nowrap; }
  .box { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px 18px; }
  .box ul { margin:6px 0; padding-inline-start:22px; }
  @media (max-width:640px) { .parts { display:none; } }
</style>
</head>
<body>
<div class="wrap">
  <h1>GCM Portal — اختبار القبول حسب الدور</h1>
  <p>لكل دور في النظام ملف اختبار لوحده. افتح الملف الخاص بالدور اللي هتختبره، وسجّل دخول بحسابه على <a href="${UAT_BASE_URL}" target="_blank" rel="noopener">${UAT_BASE_URL}</a> (كلمة السر لكل الحسابات <code>${PASSWORD}</code>).</p>

  <table>
    <tr><th>الدور</th><th>الحساب</th><th>عدد البنود</th><th>التقسيم</th></tr>${rows}
  </table>

  <h2>إيه اللي في كل ملف؟</h2>
  <div class="box">
    <ul>
      <li><strong>المرحلة التانية — الميزات الجديدة:</strong> المنشآت والخدمات والمتعهدين وشركات العملاء والمشروعات وحسابات العملاء والمتعهدين — اللي يخص الدور ده.</li>
      <li><strong>نواقص المرحلة الأولى — اللي اتكمّل:</strong> بنود طلعت في مراجعة الـFRD بعد تسليم المرحلة الأولى واتقفلت.</li>
      <li><strong>تحسينات اتضافت على اللي اتسلّم:</strong> وضوح الفورمات، تنبيهات الوثائق، التصدير، وغيرها.</li>
    </ul>
  </div>

  <h2>ملاحظات عامة</h2>
  <div class="box">
    <ul>
      <li>تقدّمك في كل ملف بيتحفظ تلقائيًا في المتصفح ده، وبعد ما تخلص دوس "تصدير Excel" في الملف عشان تبعت النتيجة.</li>
      <li>بعض بنود "حالة الحساب" محتاجة مدير النظام يغيّر حالة حسابك من نافذة تانية — مكتوب ده في البند نفسه.</li>
      <li>أي بند عطّل حساب أو حوّله لـ"في إجازة": رجّعه "نشط" قبل ما تكمّل.</li>
    </ul>
  </div>
</div>
</body>
</html>
`;
}
