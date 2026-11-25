// What goes into each per-role UAT file (docs/acceptance/uat/<role>.html) — read by build-uat.mjs.
//
// Every file has up to three parts, in this order:
//   P2 — المرحلة التانية: the phase-2 modules this role can touch (or, for the roles phase 2 introduced —
//        contractor user, client accounts — the whole role);
//   P1 — نواقص المرحلة الأولى: what the FRD review of phase 1 found missing and we then closed;
//   IM — تحسينات: client-requested or polish work added on top of what was delivered.
//
// Items are never copied here: a group lists ids (`<md file name>#<number>`) of items that already live in
// `week-*.md` (the acceptance source of truth) or in `uat-*.md` (role walkthroughs that only exist for these
// files). An id that doesn't resolve makes the build throw. Wording that only fits one tester (a step
// that says "log in as dataentry" in the data-entry file, a step that needs Platform access…) is rewritten
// through OVERRIDES / ROLE_OVERRIDES — same keys as CLIENT_OVERRIDES in build-checklist.mjs
// (title, frd, steps, stepsList, expected, expectedList). Admin and data entry also inherit
// CLIENT_OVERRIDES (written for the System-Admin-only client checklist; they fit every role that can write).

export const UAT_BASE_URL = 'https://gcm-dev.digitswat.com';
export const PASSWORD = 'password';

const range = (file, from, to) => Array.from({ length: to - from + 1 }, (_, i) => `${file}#${from + i}`);
const list = (file, ...nums) => nums.map(n => `${file}#${n}`);

const F = 'week-5-facilities';
const S = 'week-5-services';
const C = 'week-5-contractors';
const CU = 'week-5-contractor-users';
const CUP = 'week-5-contractor-user-pages';
const CO = 'week-4-client-companies';
const PR = 'week-5-projects';
const W6 = 'week-6-purchase-orders';
const W7 = 'week-7-trips';
const CA = 'week-5-client-accounts';
const AS = 'week-5-account-status';
const FC = 'week-5-form-clarity';
const DX = 'week-5-document-expiry';
const EX = 'week-5-export-header-and-category-defaults';
const U12 = 'week-1-2-auth-users-roles';
const W3 = 'week-3-vehicles-drivers-assets';
const GAP = 'week-3-frd-gap-review';
const COMMON = 'uat-common';

export const P2 = 'المرحلة التانية — الميزات الجديدة';
export const P1 = 'نواقص المرحلة الأولى — اللي اتكمّل';
export const IM = 'تحسينات اتضافت على اللي اتسلّم';
export const P3 = 'المرحلة التالتة — الرحلات';

export const PART_NOTES = {
  [P2]: 'الموديولات والصفحات اللي اتبنت في المرحلة التانية (المنشآت، الخدمات، المتعهدين، شركات العملاء، المشروعات، حسابات العملاء والمتعهدين) — اللي يخص دورك منها.',
  [P1]: 'بنود طلعت في مراجعة الـFRD بعد تسليم المرحلة الأولى واتقفلت.',
  [P3]: 'الرحلات (إنشاء ومتابعة وتعديل وإلغاء) وربطها بباقي صفحات النظام — الجزء اللي يخص دورك.',
  [IM]: 'تحسينات اتعملت بطلبك أو بعد ملاحظاتك على النسخة اللي اتسلّمت: وضوح الفورمات، تنبيهات الوثائق، التصدير، شاشات الحفظ والتحميل وغيرها.',
};

// Accounts every role file shows. `needs` = other seeded accounts a step may ask for.
export const ACCOUNTS = {
  'system-admin': { email: 'admin@gcm.test', label: 'مدير النظام' },
  'data-entry': { email: 'dataentry@gcm.test', label: 'مدخل البيانات' },
  auditor: { email: 'auditor@gcm.test', label: 'مراقب النظام' },
  driver: { email: 'driver@gcm.test', label: 'سائق' },
  'contractor-user': { email: 'contractor.desert@gcm.test', label: 'مستخدم متعهد (Desert Haulage Co.)' },
  'contractor-user-gulf': { email: 'contractor.gulf@gcm.test', label: 'مستخدم متعهد (Gulf Logistics)' },
  'client-project-manager': { email: 'client.manager@gcm.test', label: 'مدير مشروعات عميل (Al Noor Construction — كل المشروعات)' },
  'client-project-auditor': { email: 'client.auditor@gcm.test', label: 'مراقب مشروعات عميل (Al Noor Construction — مشروع Al Noor Tower)' },
};

export const ROLES = [
  // ------------------------------------------------------------------ مدير النظام
  {
    slug: 'system-admin',
    label: 'مدير النظام',
    inheritClientOverrides: true,
    account: 'system-admin',
    alsoNeeds: ['data-entry', 'auditor', 'driver'],
    intro: 'مدير النظام عنده كل الصلاحيات داخل الشركة: إنشاء وتعديل وتعطيل كل الكيانات، وهو الوحيد اللي يدير تصنيفات المركبات والإعدادات العامة. الملف ده بيغطي **اللي اتضاف بعد تسليم المرحلة الأولى**: المرحلة التانية كلها، وبنود الـFRD اللي اتكمّلت، وتحسينات اتعملت بطلبكم.',
    prereq: [
      'بعض البنود في الأخر (حالة الحساب) محتاجة تسجّل دخول بحساب تاني في نافذة تانية — الحسابات في الجدول اللي فوق، وكلمة سر الكل `password`.',
      'لو أي بند خلّى حساب "معطّل" أو "في إجازة" **رجّعه "نشط"** قبل ما تكمّل.',
    ],
    fixes: [[/بـ`admin`/g, 'بحسابك'], [/بالأدمن/g, 'بحسابك']],
    partNotes: { [P1]: 'بنود طلعت في مراجعة الـFRD بعد تسليم المرحلة الأولى واتقفلت، وبنود كانت ناقصة من قوايم الاختبار (دورة الإنشاء والتعديل وتغيير الحالة).' },
    parts: [
      {
        title: P2,
        groups: [
          { title: 'القائمة الجانبية', ids: ['uat-system-admin#1'] },
          { title: 'المنشآت الوسيطة', ids: list(F, 2, 4, 5, 6, 7, 8, 9, 11, 12) },
          { title: 'الخدمات (الأساسية والفرعية)', ids: [...list(S, 2), ...range(S, 4, 16)] },
          { title: 'المتعهدين', ids: [...list(C, 2), ...range(C, 4, 14)] },
          { title: 'مستخدمو المتعهدين وممثل المتعهد', ids: range(CU, 1, 9) },
          { title: 'شركات العملاء', ids: range(CO, 1, 9) },
          { title: 'المشروعات وإدراج الأصول', ids: list(PR, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 15, 16, 17, 21) },
          { title: 'حسابات العملاء', ids: [...range(CA, 1, 13), `${CA}#17`] },
          { title: 'التعاقدات (Purchase Orders)', ids: [...range(W6, 2, 13), ...list(W6, 15, 16, 17, 22)] },
        ],
      },
      {
        title: P3,
        groups: [
          { title: 'قايمة الرحلات والتصدير', ids: range(W7, 2, 4) },
          { title: 'إنشاء رحلة', ids: range(W7, 5, 10) },
          { title: 'تفاصيل الرحلة ومراحلها', ids: range(W7, 11, 17) },
          { title: 'ربط الرحلات بالخدمات والشركات والمركبات والتعاقدات', ids: range(W7, 24, 27) },
        ],
      },
      {
        title: P1,
        groups: [
          { title: 'الشريط العلوي ورسالة الحساب المعطّل', ids: [`${COMMON}#1`, `${GAP}#2`, `${GAP}#3`] },
          { title: 'الملف الشخصي', ids: [`${GAP}#4`, `${GAP}#28`] },
          { title: 'المستخدمين والسائقين', ids: list(GAP, 5, 6, 7, 9) },
          { title: 'استكمال دورة الإنشاء والتعديل وتغيير الحالة', ids: [...list(U12, 20, 21), ...list(W3, 23, 24, 25, 26, 27)] },
        ],
      },
      {
        title: IM,
        groups: [
          { title: 'وضوح الفورمات والرسائل', ids: list(FC, 1, 2, 3) },
          { title: 'تصنيفات المركبات (إدارة ذاتية) والحاوية الافتراضية', ids: [...list(GAP, 10, 11, 12, 13, 14, 17, 19), `${EX}#3`, `${EX}#4`, `${W3}#8`] },
          { title: 'السائقين والمركبات والأصول والسعات', ids: list(W3, 28, 18, 19, 20, 21) },
          { title: 'وثائق السائقين والمركبات وتنبيهات الانتهاء', ids: list(DX, 1, 2, 3, 4, 6, 7) },
          { title: 'حالة الحساب (تعطيل فوري / إجازة)', ids: list(AS, 2, 3, 4) },
          { title: 'التوقيع والختم لموظفي GCM وتعديل الحسابات', ids: [`${U12}#22`, `${EX}#5`] },
          { title: 'التصدير', ids: [...list(GAP, 23, 24, 25), `${EX}#1`, `${EX}#2`] },
          { title: 'الأداء وشاشات الحفظ والتحميل', ids: list(GAP, 21, 22, 29, 30) },
          { title: 'الواجهة', ids: [`${W3}#22`, `${GAP}#20`] },
        ],
      },
    ],
  },

  // ---------------------------------------------------------------- مدخل البيانات
  {
    slug: 'data-entry',
    label: 'مدخل البيانات',
    inheritClientOverrides: true,
    account: 'data-entry',
    alsoNeeds: ['system-admin', 'auditor'],
    intro: 'مدخل البيانات عنده **نفس صلاحيات مدير النظام تقريبًا** (إنشاء وتعديل وتعطيل) في كل الموديولات. الفرق: مايقدرش يلمس حساب مدير النظام ولا يدير تصنيفات المركبات ولا الإعدادات العامة. الملف ده بيغطي اللي اتضاف بعد تسليم المرحلة الأولى لدورك.',
    prereq: [
      'بنود "حسابك في إجازة" و"تعطيل الحسابات" محتاجة **مدير النظام** يغيّر حالة حسابك من نافذة تانية (`admin@gcm.test` / `password`) — أو اعمل الاتنين بنفسك في نافذتين.',
      'لو أي بند حوّل حساب لـ"معطّل" أو "في إجازة" **رجّعه "نشط"** قبل ما تكمّل.',
    ],
    fixes: [[/بـ`dataentry`/g, 'بحسابك'], [/بـ`admin`/g, 'بحسابك'], [/بالأدمن/g, 'بحسابك']],
    partNotes: { [P1]: 'بنود طلعت في مراجعة الـFRD بعد تسليم المرحلة الأولى واتقفلت، وبنود كانت ناقصة من قوايم الاختبار (دورة الإنشاء والتعديل وتغيير الحالة).' },
    parts: [
      {
        title: P2,
        groups: [
          { title: 'القائمة الجانبية وحدود الصلاحية', ids: ['uat-data-entry#1', 'uat-data-entry#2', 'uat-data-entry#3'] },
          { title: 'المنشآت الوسيطة', ids: list(F, 4, 9, 11) },
          { title: 'الخدمات (الأساسية والفرعية)', ids: list(S, 4, 10, 14) },
          { title: 'المتعهدين', ids: list(C, 4, 9, 10) },
          { title: 'مستخدمو المتعهدين وممثل المتعهد', ids: list(CU, 1, 2, 5, 7, 8) },
          { title: 'شركات العملاء', ids: list(CO, 2, 7, 8) },
          { title: 'المشروعات وإدراج الأصول', ids: list(PR, 3, 9, 10, 11, 12) },
          { title: 'حسابات العملاء', ids: list(CA, 3, 4, 8, 10) },
          { title: 'التعاقدات (مفيش وصول لمدخل البيانات)', ids: list(W6, 18) },
        ],
      },
      {
        title: P3,
        groups: [
          { title: 'قايمة الرحلات والتصدير', ids: range(W7, 2, 4) },
          { title: 'إنشاء رحلة', ids: [...range(W7, 5, 6), ...range(W7, 8, 10)] },
          { title: 'تفاصيل الرحلة ومراحلها', ids: range(W7, 11, 17) },
          { title: 'ربط الرحلات بالخدمات والشركات والمركبات', ids: range(W7, 24, 26) },
        ],
      },
      {
        title: P1,
        groups: [
          { title: 'الشريط العلوي', ids: [`${COMMON}#1`, `${GAP}#2`] },
          { title: 'الملف الشخصي', ids: ['uat-data-entry#5'] },
          { title: 'المستخدمين والسائقين', ids: list(GAP, 5, 6, 7, 9) },
          { title: 'صلاحيات مدخل البيانات (FRD V01.14)', ids: [`${GAP}#26`, `${U12}#3`] },
          { title: 'استكمال دورة الإنشاء والتعديل وتغيير الحالة', ids: [...list(U12, 20, 21), ...list(W3, 23, 24, 25, 26, 27)] },
        ],
      },
      {
        title: IM,
        groups: [
          { title: 'وضوح الفورمات والرسائل', ids: list(FC, 1, 2, 3) },
          { title: 'السائقين والمركبات والأصول والسعات', ids: [...list(W3, 28, 18, 19, 20, 8), `${EX}#4`] },
          { title: 'وثائق السائقين والمركبات وتنبيهات الانتهاء', ids: [`${COMMON}#3`, `${COMMON}#4`] },
          { title: 'حالة الحساب (تعطيل فوري / إجازة)', ids: [`${AS}#2`, 'uat-data-entry#4'] },
          { title: 'التوقيع والختم', ids: ['uat-data-entry#6'] },
          { title: 'التصدير', ids: [`${COMMON}#5`, `${COMMON}#6`, `${EX}#1`, `${EX}#2`] },
          { title: 'الأداء وشاشات الحفظ والتحميل', ids: ['uat-data-entry#7', `${GAP}#22`, `${GAP}#30`] },
          { title: 'الواجهة', ids: [`${COMMON}#2`] },
        ],
      },
    ],
  },

  // ---------------------------------------------------------------------- المراقب
  {
    slug: 'auditor',
    label: 'المراقب',
    account: 'auditor',
    alsoNeeds: ['system-admin'],
    intro: 'المراقب **يشوف ويصدّر كل صفحات النظام ومايقدرش يعدّل أو ينشئ أي حاجة**. الملف ده بيغطي اللي اتضاف بعد تسليم المرحلة الأولى لدورك: صفحات المرحلة التانية للعرض والتصدير، وحدود الصلاحية.',
    prereq: [
      'لو القوايم فاضية أو ناقصة بيانات، اطلب من مدير النظام يجهّز بيانات تجربة (مركبة، سائق، أصل، منشأة، مشروع…).',
      'بند "الأصول: في مشروع" محتاج أصل مدرج في مشروع — اطلب من مدير النظام يدرج واحد لو مفيش.',
    ],
    // The shared phase-2 items are written from the admin's side: drop the account, and trim the buttons the
    // auditor never sees (edit pencil, Edit/Create buttons).
    fixes: [
      [/\s*بـ`admin`/g, ''],
      [/بـ`auditor@gcm\.test`|بـ`auditor`/g, 'بحسابك'],
      [/عين \+ قلم/g, 'عين فقط'],
      [/\*\*أيقونة عرض وأيقونة تعديل\*\*/g, '**أيقونة عرض** (من غير أيقونة تعديل)'],
      [/ زر "Create New Sub-service" فوق\./g, ' (ومفيش زر "Create New Sub-service" للمراقب).'],
      [/وزرا Edit وBack/g, 'وزر Back (من غير Edit)'],
      [/وزر "Create New Sub-service" بيفتح الفورم والخدمة الأساسية مختارة/g, '(ومفيش زر "Create New Sub-service" للمراقب)'],
      [/زرّي Edit وBack/g, 'وزر Back (من غير Edit)'],
      [/ \+ زر "Add Project" اللي بيفتح الفورم والشركة مختارة/g, ''],
    ],
    parts: [
      {
        title: P2,
        groups: [
          { title: 'القائمة الجانبية وحدود الصلاحية', ids: ['uat-auditor#1', 'uat-auditor#2', 'uat-auditor#3'] },
          { title: 'المنشآت الوسيطة', ids: list(F, 2, 3, 8, 12) },
          { title: 'الخدمات (الأساسية والفرعية)', ids: list(S, 2, 3, 5, 9, 8) },
          { title: 'المتعهدين', ids: list(C, 2, 3, 8, 14) },
          { title: 'شركات العملاء', ids: list(CO, 1, 6, 11, 9) },
          { title: 'المشروعات والأصول', ids: [...list(PR, 2, 7, 8, 15, 16, 17), 'uat-auditor#5'] },
          { title: 'حسابات العملاء والمتعهدين في قايمة المستخدمين', ids: ['uat-auditor#4'] },
          { title: 'التعاقدات (Purchase Orders)', ids: list(W6, 2, 3, 4, 11, 19) },
        ],
      },
      {
        title: P3,
        groups: [
          { title: 'قايمة الرحلات والتصدير', ids: range(W7, 2, 4) },
          { title: 'تفاصيل الرحلة والمستندات', ids: list(W7, 11, 17) },
          { title: 'أرقام الرحلات في الشركات والخدمات والمركبات والتعاقدات', ids: range(W7, 25, 27) },
        ],
      },
      {
        title: P1,
        groups: [
          { title: 'الشريط العلوي', ids: [`${COMMON}#1`, `${GAP}#2`] },
          { title: 'الملف الشخصي', ids: ['uat-auditor#6'] },
          { title: 'صلاحيات المراقب (FRD V01.14)', ids: [`${GAP}#27`] },
        ],
      },
      {
        title: IM,
        groups: [
          { title: 'وثائق السائقين والمركبات وتنبيهات الانتهاء', ids: [`${COMMON}#3`, `${COMMON}#4`] },
          { title: 'التصدير', ids: [`${COMMON}#5`, `${COMMON}#6`, `${EX}#1`, `${EX}#2`] },
          { title: 'الواجهة وشاشة التحميل', ids: [`${COMMON}#2`, `${GAP}#30`] },
        ],
      },
    ],
  },

  // ----------------------------------------------------------------------- السائق
  {
    slug: 'driver',
    label: 'السائق',
    account: 'driver',
    alsoNeeds: ['system-admin'],
    intro: 'السائق بيسجّل رحلاته بنفسه (بكاميرا وموقع حيّين) ويتابعها ويرفع صورة إيصال التدوير. الملف ده بيغطي اللي اتضاف لدورك بعد تسليم المرحلة الأولى.',
    prereq: ['بنود "حالة الحساب" محتاجة مدير النظام يغيّر حالة حسابك من نافذة تانية (`admin@gcm.test` / `password`).'],
    fixes: [],
    parts: [
      {
        title: P2,
        groups: [{ title: 'القائمة الجانبية وحدود الصلاحية', ids: ['uat-driver#1', 'uat-driver#2'] }],
      },
      {
        title: P3,
        groups: [
          { title: 'رحلاتي', ids: range(W7, 18, 20) },
        ],
      },
      {
        title: P1,
        groups: [
          { title: 'الشريط العلوي', ids: [`${COMMON}#1`, `${GAP}#2`] },
          { title: 'الملف الشخصي', ids: ['uat-driver#3'] },
        ],
      },
      {
        title: IM,
        groups: [
          { title: 'حالة الحساب (تعطيل فوري / إجازة)', ids: ['uat-driver#4', 'uat-driver#5'] },
          { title: 'الواجهة وشاشة التحميل', ids: [`${COMMON}#2`, `${GAP}#30`] },
        ],
      },
    ],
  },

  // ----------------------------------------------------------------- مستخدم المتعهد
  {
    slug: 'contractor-user',
    label: 'مستخدم المتعهد',
    account: 'contractor-user',
    alsoNeeds: ['contractor-user-gulf', 'system-admin'],
    intro: 'مستخدم المتعهد **عرض وتصدير فقط** لموارد متعهده: سائقيه ومستخدمي المتعهد التابعين له ومركباته وأصوله (حاويات وصهاريج). الدور ده اتبنى في المرحلة التانية، ورحلات متعهده اتضافت في المرحلة التالتة.',
    prereq: [
      'القوايم هتكون فاضية لحد ما مدير النظام (`admin@gcm.test`) يجهّز بيانات التجربة: **مركبة وسائق وأصل تابعين لـ Desert Haulage Co.**، و**مركبة وسائق تابعين لـ Gulf Logistics**، و**مركبة تابعة لـ GCM**.',
      'بنود "حالة الحساب" محتاجة مدير النظام يغيّر حالة حسابك من نافذة تانية.',
    ],
    fixes: [],
    parts: [
      {
        title: P2,
        groups: [
          { title: 'القائمة الجانبية والصفحات', ids: list(CUP, 1, 2, 3) },
          { title: 'نطاق الرؤية (متعهدك بس)', ids: [...list(CUP, 4, 5, 6, 7), 'uat-contractor-user#1'] },
          { title: 'التصدير', ids: [`${CUP}#8`] },
          { title: 'الملف الشخصي', ids: ['uat-contractor-user#2'] },
          { title: 'حالة الحساب (تعطيل فوري / إجازة)', ids: ['uat-contractor-user#3', 'uat-contractor-user#4'] },
        ],
      },
      {
        title: P3,
        groups: [
          { title: 'رحلات متعهدي', ids: range(W7, 21, 23) },
        ],
      },
      {
        title: P1,
        groups: [{ title: 'الشريط العلوي', ids: [`${COMMON}#1`, `${GAP}#2`] }],
      },
      {
        title: IM,
        groups: [
          { title: 'وثائق السائقين والمركبات وتنبيهات الانتهاء', ids: [`${COMMON}#3`, `${COMMON}#4`] },
          { title: 'التصدير', ids: [`${COMMON}#5`, `${COMMON}#6`, `${EX}#1`, `${EX}#2`] },
          { title: 'الواجهة وشاشة التحميل', ids: [`${COMMON}#2`, `${GAP}#30`] },
        ],
      },
    ],
  },

  // ------------------------------------------------------------- حسابات العملاء (2)
  ...[
    {
      slug: 'client-project-manager',
      label: 'مدير مشروعات العميل',
      account: 'client-project-manager',
      scope: 'حسابك تابع لشركة **Al Noor Construction** بصلاحية على **كل مشروعاتها** (الحالية والمستقبلية).',
    },
    {
      slug: 'client-project-auditor',
      label: 'مراقب مشروعات العميل',
      account: 'client-project-auditor',
      scope: 'حسابك تابع لشركة **Al Noor Construction** بصلاحية على **مشروع محدد (Al Noor Tower)** بس.',
    },
  ].map(r => ({
    ...r,
    alsoNeeds: ['system-admin'],
    intro: `${r.scope} صفحات العميل الفعلية (الرحلات والتقارير، وتعاقدات مشروعاتك لمدير المشروعات) — التعاقدات اتبنت والباقي جاي مع موديولاته في مرحلة لاحقة — حاليًا الحساب بيدخل ويظبط ملفه الشخصي بس. الدور ده اتبنى في المرحلة التانية — فكل الملف تحتها.`,
    prereq: ['بنود "حالة الحساب" محتاجة مدير النظام يغيّر حالة حسابك من نافذة تانية (`admin@gcm.test` / `password`).'],
    fixes: [],
    parts: [
      {
        title: P2,
        groups: [
          { title: 'الدخول وحدود الصلاحية', ids: ['uat-client#1', 'uat-client#2'] },
          { title: 'الملف الشخصي (صورة، توقيع وختم، كلمة السر)', ids: [`${CA}#14`] },
          ...(r.slug === 'client-project-manager' ? [{ title: 'التعاقدات (تعاقدات مشروعاتك)', ids: list(W6, 20) }] : []),
          { title: 'حالة الحساب (تعطيل فوري / إجازة)', ids: ['uat-client#3', 'uat-client#4'] },
        ],
      },
      {
        title: P1,
        groups: [{ title: 'الشريط العلوي', ids: [`${COMMON}#1`, `${GAP}#2`] }],
      },
      {
        title: IM,
        groups: [{ title: 'الواجهة وشاشة التحميل', ids: [`${COMMON}#2`, `${GAP}#30`] }],
      },
    ],
  })),
];

// ---------------------------------------------------------------------------------------------------
// Rewrites for items whose wording assumes a tester/role other than the file's own. Applied to every role
// (OVERRIDES) or only one (ROLE_OVERRIDES[slug]); keys as in CLIENT_OVERRIDES.

export const OVERRIDES = {
  // These say "log in as dataentry" in the source (they're the data-entry walkthrough there); admin and data
  // entry do the same flow, so the UAT wording is account-neutral.
  [`${F}#4`]: {
    title: 'إنشاء منشأة لكل نوع خدمة',
    stepsList: [
      'افتح "إنشاء منشأة جديدة" (Operations ← Facilities ← Add).',
      'أنشئ ثلاث منشآت: واحدة "الدفن الآمن" وواحدة "معالجة مياه الصرف" وواحدة "إعادة التدوير".',
      'املأ الاسم والاختصار والعنوان وارفع لوجو.',
    ],
  },
  [`${F}#11`]: {
    stepsList: [
      'افتح تفاصيل منشأة نشطة ← كارت "حالة المنشأة" ← اختار "Deactivated" ← علّم "I confirm" ← "Update Status".',
      'بعدها أعد تنشيطها بنفس الطريقة.',
    ],
  },
  [`${C}#4`]: {
    stepsList: ['افتح Contractors ← Add.', 'جرّب "Submit" والفورم فاضي.', 'بعدين اكتب اسم "Test Hauler" واسم مختصر `tst` واحفظ.'],
  },
  [`${CA}#14`]: {
    expected:
      'فيه 3 كروت: الصورة، **Signature & Stamp**، Change Password. رفع صورة واحدة بيسيب التانية زي ما هي. من غير اختيار صورة بتظهر رسالة "Choose a signature or stamp image first". الاسم والبريد للقراءة فقط. القائمة الجانبية فاضية (الملف الشخصي من أيقونة الحساب أعلى الصفحة). لو كتبت يدويًا رابط صفحة قايمة زي `/app/user/list` بتفتح فاضية من غير أي صف بيانات (برسالة "You don\'t have permission to view this data." أو "No … found" حسب الصفحة)، وصفحات الإنشاء والتعديل بترجّع 403. السائق ومستخدم المتعهد مفيش عندهم كارت التوقيع والختم (ده لحسابات العميل وموظفي GCM).',
  },
  [`${GAP}#6`]: {
    stepsList: [
      'افتح فورم إنشاء مستخدم (Users ← Add User ← GCM Staff).',
      'افتح فورم إنشاء سائق (Drivers ← Add).',
      'افتح صفحة تعديل وتفاصيل كل واحد منهم.',
      'كرّر بالعربي والإنجليزي.',
    ],
  },
};

export const ROLE_OVERRIDES = {
  'system-admin': {
    [`${GAP}#3`]: {
      stepsList: [
        'من Users ← Add User ← GCM Staff أنشئ حساب تجربة (مراقب مثلًا) ببريد جديد وكلمة سر تعرفها.',
        'افتح تفاصيل الحساب الجديد وغيّر حالته لـ"Deactivated" ودوس "Update Status".',
        'اطلع (Logout) وسجّل دخول بالحساب الجديد بكلمة السر **الصح**.',
        'جرّب نفس البريد بكلمة سر **غلط**.',
      ],
    },
    [`${GAP}#28`]: {
      title: 'كلمة السر الذاتية من الملف الشخصي',
      stepsList: [
        'افتح My Profile من أيقونة حسابك أعلى الصفحة.',
        'من كارت "Change Password" اكتب كلمة السر الحالية (`password`) وكلمة جديدة مرتين واحفظ.',
        'اخرج وادخل بالكلمة الجديدة، وبعدين رجّعها `password`.',
        'جرّب تكتب كلمة حالية غلط.',
      ],
      expected:
        'الكارت ظاهر لك (وكذلك لمدخل البيانات والمراقب والسائق). تغيير كلمة السر بينجح ("Password updated")، والكلمة الحالية الغلط بتترفض، والاسم والبريد لسه للقراءة فقط. (المراقب والسائق من نص الـFRD؛ مدير النظام ومدخل البيانات توسيع بطلب العميل.)',
    },
  },
  'data-entry': {
    [`${GAP}#26`]: {
      stepsList: [
        'افتح أي مستخدم (أو سائق) وغيّر حالته لـ"معطّل"، وبعدين رجّعه "نشط".',
        'كرّر نفس الشيء على أي مركبة، وأي أصل.',
      ],
      expected:
        'كل التعطيلات وإعادة التنشيط تنجح. حساب مدير النظام مش ظاهر ليك أصلًا ومينفعش تلمسه (شوف "حساب مدير النظام محمي منك" فوق).',
    },
    [`${EX}#4`]: {
      stepsList: [
        'اطلب من مدير النظام يضبط على أي تصنيف قيم الحاوية الافتراضية (Vehicles ← Categories ← تعديل ← "Has an embedded container" + Container أو Tank + سعة).',
        'افتح Vehicles ← Add Vehicle واختار التصنيف ده. غيّر النوع والسعة يدويًا قبل الحفظ، واحفظ المركبة.',
        'افتح تعديل مركبة قديمة (عمرها ما اتأثرت بالتصنيف)، وبدّل تصنيفها لنفس التصنيف.',
        'اطلب من مدير النظام يغيّر القيم الافتراضية للتصنيف، وبعدها افتح المركبة اللي اتحفظت.',
      ],
    },
    [`${GAP}#5`]: {
      stepsList: [
        'بحسابك عدّل واحفظ مستخدم، وسائق، ومركبة، وأصل (كل واحد لوحده).',
        'بعد كل حفظ، افتح صفحة **التفاصيل** ثم صفحة **التعديل** لنفس السجل.',
      ],
      expected:
        'في الصفحتين سطر صغير "Last updated by Data Entry — <التاريخ والوقت>" باسمك. لو السجل عمره ما اتعدّل من واجهة (بيانات زرع أولية) السطر مش بيظهر.',
    },
    [`${U12}#3`]: {
      title: 'تحط كلمة سر جديدة لمستخدم أو سائق غيرك',
      frd: '§1.4 ("كلمة المرور قابلة للتعديل من قبل مدير النظام او مدخل البيانات")',
      stepsList: [
        'من Users افتح تعديل `auditor@gcm.test` — اكتب كلمة سر جديدة مع تأكيدها واحفظ. بعدين سجّل دخول بالمراقب بالكلمة الجديدة (ورجّعها `password` بعد الاختبار).',
        'كرّر نفس الخطوة على سائق من صفحة تعديل السائق.',
        'سيب خانة كلمة السر فاضية وعدّل الاسم بس واحفظ — كلمة السر القديمة المفروض تفضل شغالة.',
      ],
      expected: 'الكلمة القديمة ترفض والجديدة تدخل. وفي الحالة التالتة مفيش تغيير في كلمة السر.',
    },
    [`${AS}#2`]: {
      stepsList: [
        'حسابك مش ظاهر في قايمة Users (محدّش بيشوف صفه هو). افتح بإيدك `/app/user/view/2` (رقم حسابك في بيانات التجربة) وبعدها `/app/user/edit/2`.',
      ],
    },
  },
};
