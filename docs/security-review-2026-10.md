# مراجعة الأمان — أكتوبر 2026

> نطاق المراجعة: الاعتماديات (Composer + npm)، الإعدادات، المصادقة والجلسات والتوكنات، الصلاحيات وعزل الـtenants، رفع وتنزيل الملفات، XSS في الواجهة والتصدير، حقن الصيغ في Excel، الحدّ من الطلبات، رؤوس الاستجابة. الفرع: `phase_2_merge`.
> المنهج: قراءة الكود + تشغيل `composer audit` / `npm audit` + إثبات كل ثغرة بتست قبل/بعد الإصلاح. كل بند تحت له تست في `tests/Feature/Security/*` أو `tests/Unit/RichTextTest.php`.

## الخلاصة

| # | الخطورة | المشكلة | الحالة |
|---|---|---|---|
| 1 | **عالية** | XSS مخزَّن في "البيانات الإضافية" (`additional_data`) — يتحوّل لتصعيد صلاحيات (`data_entry` ← `system_admin`) | ✅ اتقفلت |
| 2 | **عالية** | صفحات سقالة Vuexy مفتوحة لأي زائر، ومعاها CRUD تجريبي للمستخدمين (`/user-list`) | ✅ اتقفلت |
| 3 | **عالية** | دخول Super Admin (`POST /platform/login`) من غير أي حد للمحاولات | ✅ اتقفلت |
| 4 | **عالية** | اعتماديات فيها ثغرات معروفة: Laravel 12.67 (3 advisories منها critical)، Excel، commonmark، symfony | ✅ اتحدّثت |
| 5 | متوسطة | حقن صيغ في تصدير Excel (نص يبدأ بـ`=` بيتكتب Formula) | ✅ اتقفلت |
| 6 | متوسطة | كشف الإيميلات المسجّلة (نسيت كلمة السر + زمن الدخول) | ✅ اتقفلت |
| 7 | متوسطة | تسميم رابط استرجاع كلمة السر عبر Host header | ✅ اتقفلت (`TrustHosts`) |
| 8 | متوسطة | توكنات الموبايل ماتنتهيش أبدًا، وتغيير كلمة السر مابيقفلش الجلسات/التوكنات الأخرى | ✅ اتقفلت |
| 9 | متوسطة | مفيش حد للطلبات على الـAPI، وحد الدخول بالإيميل+IP فقط (تجربة حسابات كتير من جهاز واحد) | ✅ اتقفلت |
| 10 | متوسطة | Seeders بتزرع حسابات باسورد `password` — خطر لو اتشغّلت على production | ✅ اتقفلت (بترفض) |
| 11 | منخفضة–متوسطة | قواعد `exists:vehicle_categories,id` من غير عزل tenant (تصنيف شركة تانية + كشف وجود الـid) | ✅ اتقفلت |
| 12 | منخفضة | غياب رؤوس الأمان (framing، nosniff، referrer، HSTS…) | ✅ اتضافت |
| 13 | منخفضة | `/lang/{locale}` بيرجّع لأي موقع في الـReferer (open redirect) | ✅ اتقفلت |
| 14 | منخفضة | الكوكي مش `Secure` افتراضيًا في production | ✅ اتقفلت |
| 15 | منخفضة | ثغرات npm (axios، vite، postcss، lodash، moment، katex، glob…) | ✅ اتحدّثت (باقي تنبيهات موثّقة تحت) |

## التفاصيل

### 1. XSS مخزَّن عبر `additional_data`
**المشكلة:** عمود "البيانات الإضافية" (محرر Quill) بيتخزَّن زي ما هو، وعشر صفحات عرض بتحطه في `innerHTML`. المحرر في الفورم مش حد أمان — أي حساب `data_entry` يقدر يبعت `<img src=x onerror=...>` للـAPI مباشرة، والسكريبت يشتغل في متصفح مدير النظام اللي يفتح السجل (يقدر يعمل أي طلب باسمه: تغيير باسورد، إنشاء حسابات…).
**الإصلاح (3 طبقات):**
- `App\Support\RichText::sanitize()` — allowlist: `p br strong b em i u s strike ol ul li blockquote pre code h1-h6 span a sub sup div`، كلاسات `ql-*` بس، روابط `http/https/mailto/tel` بس (مع كشف الفراغات/الأحرف الخفية جوه الـscheme)، ألوان `color`/`background-color` بس. المخرَج بيتبني من الصفر (النص escaped) مش بيتنسخ من الـparser، فمفيش mutation-XSS. بيشيل `script/style/iframe/svg/math/img/…` بمحتواها.
- `NormalizesRichTextInput` (في كل الـ21 request) بينادي عليه عند الإدخال.
- `App\Casts\SanitizedRichText` على `additional_data` في 10 موديلات — بينضّف عند **القراءة** كمان، فالصفوف القديمة (اللي ممكن تكون محقونة فعلًا) مش بتوصل للمتصفح.
**أثر وظيفي:** الصور والفيديو المضمَّنة في المحرر (لو اتلصقت) بتتشال. محتوى Quill العادي (عريض، مائل، قوائم، عناوين، روابط، ألوان، محاذاة) بيعدّي زي ما هو.
**التست:** `RichTextTest` (حالات هجوم + idempotency) و`StoredXssTest`.

### 2. صفحات الديمو
`routes/web.php` كان بيسجّل ~150 صفحة من قالب Vuexy من غير `auth`، ومعاها `Route::resource('/user-list', UserManagement::class)` (store/destroy على المستخدمين). الـtenant scope كان بيرمي استثناء لأن مفيش tenant، فالتأثير الفعلي كان 500 مش تسريب — لكنها سطح هجوم مفتوح. دلوقتي الكل جوه `if (SHOW_DEMO_MENU && ! production)` + `auth` + system_admin، والـ`UserManagement` اتحذف. **التست:** `DemoRoutesTest`.

### 3–9. المصادقة والجلسات والحدود
- `platform-login` limiter (5/دقيقة لكل إيميل+IP، 30/دقيقة لكل IP)؛ نفس الـIP-limit اتضاف على `login` و`password-reset`.
- `api` limiter لكل مستخدم/توكن (`API_RATE_LIMIT`=300/دقيقة) + `EXPORT_RATE_LIMIT`=20 للتصدير.
- `forgot-password` بيرد نفس الرد دايمًا؛ الـlogin بيعمل `Hash::check` ضد hash وهمي لو الإيميل مجهول.
- `trustHosts` (host الـ`APP_URL` + ساب-دومينات + `TRUSTED_HOSTS`) خارج `local`.
- `sanctum.expiration` = 30 يوم؛ `User::booted()` بيقفل باقي التوكنات/الجلسات/remember-me عند أي تغيير باسورد (`RevokeUserSessions`).
**التستات:** `RateLimitingTest`، `AccountEnumerationTest`، `PasswordChangeRevokesAccessTest`، `CredentialHardeningTest`، `SecurityHeadersTest`.

### 5. حقن صيغ Excel
`SafeExcelValueBinder` (مسجّل في `excel.value_binder.default`) — النص اللي يبدأ بـ`=` بيتكتب نص. **التست:** `ExcelFormulaInjectionTest` بيقرا الملف الناتج ويتأكد إن مفيش خلية Formula.

### 4. الاعتماديات
| الحزمة | قبل | بعد | الثغرة |
|---|---|---|---|
| laravel/framework | 12.67.0 | 12.70.1 | CVE-2026-114509 (TokenGuard — الـ app بيستخدم Sanctum فمش مستغَلة مباشرة)، CVE-2026-114508، CVE-2026-102279 (XSS صفحة الـdebug) |
| maatwebsite/excel | 3.1.67 | 3.1.70 | CVE-2026-84374 |
| league/commonmark | 2.10.0 | 2.10.4 | تخطي DisallowedRawHtml + DoS |
| symfony/http-foundation / yaml | 7.4.17 | 7.4.21 | CVE-2026-108082 / CVE-2026-109413 |
| axios، vite، postcss، lodash، moment، katex، glob، ajv، @babel/core | — | آخر نسخة بنفس الـmajor | تنبيهات npm |

## المخاطر المتبقية (قرارك)

1. **`swiper` (critical، prototype pollution):** مستخدم في صفحات الديمو بس (`cards-advance`, `dashboards-analytics`, `front-page-landing`, `ui-carousel`) وكلها مش متسجّلة دلوقتي. الترقية لـ14 تغيير كبير ومالهاش داعي طالما الصفحات دي برّه.
2. **`quill` 1.3.7 (XSS في المحرر نفسه):** الترقية لـ2.x تمس 20+ فورم. الخطر على المخزَّن اتقفل بالمعقّم؛ المتبقي يخص المحرر وهو بيعرض.
3. **`sweetalert2`:** التحديث لآخر نسخة بيكسر `sweetalert2.scss` (`$swal2-width * 0.5`) — اتسابت على 11.10.8 (التنبيه "low").
4. **أدوات البناء والتطوير** (`vite` 5.4 وdev-server بتاعه، `esbuild`، `laravel-vite-plugin`، `nodemon`، `semver`، `browser-sync`) — `npm audit` بيعدّها high/moderate لكن كلها بتشتغل على جهاز المطوّر وقت `npm run build`/`vite` فقط؛ المخرَج `public/build` ملفات ثابتة والـserver بتاع الإنتاج مايشغّلهاش. إصلاحها ترقية major لـvite (6+) وإعادة اختبار الـbuild. `katex` (low): الإصلاح في 0.19 (major).
5. **سياسة كلمة السر:** 8 حروف على الأقل بس. التشديد (حروف كبيرة/أرقام) قرار منتج؛ تستات كتير بتستخدم باسوردات بسيطة.
6. **تحقق ثنائي (2FA)** لمدير النظام والـSuper Admin — مش موجود.
7. **CSP من غير `script-src`:** القوالب والصفحات بتعتمد على inline scripts. تفعيل `script-src` محتاج نقلها لملفات أو nonces.
8. **إيميل استرجاع كلمة السر بيتبعت synchronously:** فرق زمن الرد بين إيميل موجود وغير موجود لسه قابل للقياس من الشبكة. الحل الكامل: Queue worker (cron على Hostinger).
9. **`trustProxies`:** لو الاستضافة قدام proxy/CDN لازم يتظبط، وإلا حدود الطلبات بالـIP هتشوف عنوان الـproxy.
10. **الجلسات بـ`file` driver** مش بتتقفل عن بُعد عند تغيير الباسورد (بتشتغل مع `database` المستخدم حاليًا).
11. **صور الملف الشخصي والشعارات** على القرص `public` بروابط عشوائية من غير تسجيل دخول — قرار تصميمي قديم؛ المستندات والتوقيع/الختم على القرص الخاص ومحمية بـPolicy.

## اللي اتراجع ومالقيناش فيه مشكلة
عزل الـtenants بالـglobal scope (بيرمي استثناء لو مفيش tenant)، Policy على كل endpoint، تنزيل الملفات الخاصة بـ`Gate`، قواعد الرفع (`image` من غير SVG، `mimes:pdf,jpg,jpeg,png` + حدود حجم)، عدم وجود SQL خام بمدخلات مستخدم (أعمدة الترتيب allowlist)، escape في Blade وDataTables والـPDF، DomPDF بـ`enable_remote=false` و`enable_php=false`، `$fillable` على الموديلات، حماية CSRF للـSPA، وملف `.env` مش متتبَّع في git.
