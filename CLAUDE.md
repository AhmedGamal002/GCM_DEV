# CLAUDE.md — مرجع عملي سريع لهذا المشروع

> ملف تشغيلي، مش وثيقة تخطيط — للـ "ليه القرار ده" و"إيه الخطة" شوف `ARCHITECTURE.md` و`WEEKLY_PLAN.md`. الملف ده لأي حاجة اتكشفت بالتجربة الفعلية وممكن تضيّع وقت لو اتنسيت.

## الحالة الحالية

- **الأسبوع 1 (Tenant/Auth) و2 (المستخدمين/الأدوار) خلصوا بالكامل** — 70 Feature test شغالة. راجع `WEEKLY_PLAN.md` لتفاصيل كل أسبوع وإضافاته.
- **الأسبوع 3 — موديول المركبات خلص، والسائقين بقى فيه "المركبة الافتراضية" كمان** (133 Feature test إجمالًا). **موديول مستقل top-level زي Users** — `app/Domain/Vehicles/`، مسار `/app/vehicle/*`، عنصر قائمة "Vehicles" مسطّح؛ **مفيش مجموعة "Fleet"**. **البنية:** views في `resources/views/tenant/vehicles/`، web controllers في `app/Http/Controllers/Web/Vehicles/`، routes في `routes/tenant.php` (مطلوبة من `web.php`). **إنشاء/تعديل سائق حصريًا عن طريق `/api/v1/drivers`** (مش category جوه فورم المستخدمين العام — القرار ده اتصحح 3 مرات، راجع `ARCHITECTURE.md §5` قبل ما تفتح النقاش تاني). الأصول لسه ناقصة. راجع `WEEKLY_PLAN.md` أسبوع 3 لتفاصيل كاملة.
- **إعداد بيئة أول مرة على نسخة جديدة:** `composer install` + `php artisan storage:link` + احذف `public/hot` لو موجود (بيخلي الأصول تحاول تحمّل من vite dev server مش من `public/build`) + `APP_URL=http://localhost:8000` (بالبورت). `maatwebsite/excel` و`barryvdh/laravel-dompdf` في الـ lock لكن التصدير بيرمي 500 لو `vendor/` مش متزامن.
- Company/Contractor مؤجلين للأسبوع 4-5 — أي كود بيفترض وجودهم (فروع الفورم، أدوار العميل) مش مبني لسه عمدًا.
- **فيه Postman collection كاملة في `postman/`** (راجع `postman/README.md`) بتغطي كل الـ endpoints الشغالة — لازم تتحدث فورًا مع أي route جديد، نفس لحظة إضافته لـ `routes/api.php`/`routes/platform.php`.

## اتفاقيات ثابتة — لازم تتطبق على أي صفحة جديدة

- **أي صفحة محتوى جديدة (`@section('content')`) لازم تبدأ بـ breadcrumb** — `@include('_partials.breadcrumb', ['breadcrumbs' => [...]])`. الـ partial موجود في `resources/views/_partials/breadcrumb.blade.php`، بياخد array من `['title' => ..., 'url' => ...]` (آخر عنصر أو أي عنصر من غير `url` بيتعرض كـ active/مش رابط تلقائيًا)، وبارامتر اختياري `homeUrl` لو الصفحة تحت `routes/platform.php` (استخدم `route('platform.dashboard')` بدل الافتراضي `url('/')`). راجع أي صفحة من صفحات Users/Drivers/Platform الحالية كمثال جاهز.
- صفحات التينانت بتستخدم `__()` للعناوين (ثنائية اللغة)، صفحات الـ Platform إنجليزي ثابت بدون `__()` — خلي الـ breadcrumb متسق مع نفس الصفحة (متستخدمش `__()` في breadcrumb صفحة Platform).
- **مكان الملفات — أي موديول جديد (Fleet/Assets/Companies/...) لازم يتبع نفس البنية المستخدمة لـ Users/Drivers، مش يترمي وسط سقالة Vuexy الديمو**:
  - View: `resources/views/tenant/{module}/{page}.blade.php` (مثلاً `tenant/fleet/list.blade.php`)
  - Controller: `App\Http\Controllers\Web\{Module}\{Page}Controller` (namespace بحرف كابيتال، مطابق لباقي الموديولات)
  - Route: يضاف في `routes/tenant.php`، **مش** `routes/web.php` (الملف ده سقالة Vuexy الأصلية، يفضل زي ما هو)
  - Export (PDF/Excel): `app/Domain/{module}/Exports/{Module}Export.php` (مش `app/Exports/` عام) + قالب PDF في `resources/views/tenant/{module}/export-pdf.blade.php`
  - راجع `ARCHITECTURE.md §9.5` للتفاصيل والسبب (كان فيه 47 ملف ديمو مختلط مع 8 حقيقيين بس في مجلد واحد قبل الفصل ده).
  - **`app/Models/` مقصود يفضل flat** — مش نفس القاعدة، ده استثناء واعي (تفاصيل في `ARCHITECTURE.md §9.5`)، متقترحش نقله تلقائي.

## قبل ما تبدأ أي تعديل

- الـ FRD المرجعي الفعلي هو **أحدث نسخة `.docx` موجودة في جذر المشروع** (اتأكد من رقم النسخة كل مرة — كانت V01.06، بقت V01.09 بعد إضافة أقسام Vehicles/Fleet) — **مش** الـ `.pdf` (نصه العربي مش قابل للاستخراج، الخط مشفّر بشكل غريب). لو محتاج تقرأه، فك ضغط الملف كـ zip واستخرج `word/document.xml`، بعدين parse كـ XML — الجداول (`w:tbl`) عناصر منفصلة عن الفقرات (`w:p`)، لازم تمشي على children بالترتيب لو عايز الترتيب الطبيعي للمحتوى محفوظ.
- الداتابيز المحلية (`gcm_dev`) مش معزولة عن التستات — تأكد دايمًا تعمل `php artisan migrate:fresh --seed` في الآخر بعد أي تجربة يدوية بالمتصفح، عشان الداتا التجريبية متتراكمش.

## فخاخ بيئة التطوير (تتسبب في وقت ضايع لو اتنسيت)

1. **أي تعديل على `.env` محتاج إعادة تشغيل السيرفر بالكامل** (مش `config:clear` بس) — `php artisan serve --no-reload` بيستخدم `PHP_CLI_SERVER_WORKERS`، والـ workers بتحتفظ بقيم `putenv()` القديمة على مستوى الـ process. الطريقة: `preview_stop` ثم `preview_start` تاني (أو لو البورت 8000 متمسك بعملية `php.exe` قديمة معلّقة، اقفلها الأول بـ PowerShell `Stop-Process`).
2. **`APP_URL` لازم يشمل البورت** (`http://localhost:8000`) — من غيره أي رابط بيتولّد من `Storage::disk('public')->url()`/`url()`/`route()` بيبقى غلط (بورت 80 مش 8000)، وده بيبان وكإنه "الملف مش بيتحفظ" رغم إنه محفوظ فعليًا على القرص.
3. **الـ `computer` tool (click/type) مش موثوق فيه دايمًا في البيئة دي** — كتير من الأحيان بيحصل click من غير ما يوصل فعليًا (الـ focus بيفضل على `<body>`). لو حصل كده مرتين، متكررش المحاولة — استخدم `javascript_tool` تقرأ الـ value بعد كل خطوة عشان تتأكد، ولو فشل استمر، استخدم `el.value = ...; el.dispatchEvent(new Event('input', {bubbles:true}))` + `form.requestSubmit()` كبديل موثوق.
4. **صفحات فيها أكتر من `<form>`** (زي أي صفحة فيها logout form مخفي في الـ navbar) — `document.querySelector('form')` بيمسك أول واحد في ترتيب الـ DOM مش بالضرورة اللي إنت قاصده. استخدم selector أدق زي `form[action*="..."]`.
5. **`Storage::fake('public')` في التستات بيسقط إعداد `'url'` المخصص** من `config/filesystems.php` — تستات رفع الصور تتأكد من وجود الملف + جزء من الرابط، مش من كونه absolute URL كامل (ده مضمون بس في التشغيل الحقيقي).
6. **`php artisan migrate:fresh` بيصفّر جدول `sessions`** — أي تبويب متصفح مفتوح هيترمي على صفحة تسجيل الدخول تاني بعدها.
7. **Laravel بيحتفظ بجلسة كل guard (`web`/`platform`) بشكل مستقل** — تسجيل دخول على guard مايسجّلش خروج من التاني. أي قرار UI بيعتمد على "مين المستخدم الحالي" (زي القائمة الجانبية) لازم ياخد في الاعتبار الرابط الحالي كمان، مش بس "هل فيه جلسة platform شغالة".
8. **`php artisan route:list` مكسور من قبل أي تعديل من الجلسة دي** — بيرمي `ReflectionException: Class "App\Http\Controllers\layouts\NavbarFull" does not exist` (راوت ديمو Vuexy بيشاور على كنترولر مش موجود أصلاً في المشروع). متحاولش تصلحه، ومتستخدموش للتحقق — استخدم `curl`/المتصفح مباشرة على الراوتات اللي محتاج تتأكد منها.
9. **صفحة الديمو `/auth/reset-password-basic`** (مش الصفحة الحقيقية `/reset-password/{token}`) بترمي 500 (`Undefined variable $token`) — الكنترولر الديمو (`authentications\ResetPasswordBasic`) بيرندر نفس الـ view بتاع الصفحة الحقيقية من غير ما يمرر `token`/`email`. موجود من الأصل، مش حاجة اتكسرت، مش أولوية تتصلح.
10. **أي تست بيستخدم `actingAs($user, 'web')` بيتجاوز الـ guard/middleware pipeline بالكامل** — مبيثبتش إن المصادقة الحقيقية (session cookie فعلي، أو Bearer token فعلي) شغالة. اتكشف بيه باگ حرج فعلي (كل طلب بتوكن كان بيرمي 500 على أي endpoint تينانتي — راجع `ARCHITECTURE.md §3.11`) فضل مخفي لحد ما اتحصل عليه بتوكن حقيقي عن طريق Postman/curl. أي auth provider مخصص بيغطي guard واحد بس — لو فيه أكتر من مسار مصادقة لنفس الموديل (session + token)، كل مسار محتاج فحص/فيكس منفصل، ومفيش بديل عن تجربة فعلية بتوكن حقيقي (مش `actingAs`) لتغطية مسار الـ API الحقيقي.

## قواعد كود اتأكدت أهميتها بالتجربة (مش نظرية)

- **أي DataTables `render` callback بيرجّع HTML خام لازم يعمل escape لأي حقل مصدره مستخدم** (اسم، إيميل، أي self-service field). DataTables بتحقن الناتج بـ `.html()` مش كنص — استخدم `$('<div>').text(value).html()`. اكتشفنا XSS مخزّن حقيقي بالطريقة دي (مستخدم بأقل صلاحية غيّر اسمه بنفسه، ونفّذ كود في جلسة الـ system_admin وهو بيفتح جدول المستخدمين).
- **أي Model بيستخدم `getRoleNames()`/Spatie `HasRoles`، لازم تعمل `->with('roles')` صريح في أي query بيرجّع مجموعة (list/export)** — من غيرها N+1 كامل (`HasRoles::getRoleNames()` بتعمل `loadMissing('roles')` تلقائيًا لو مش eager-loaded).
- **أي `withCount()` أو subquery على علاقة بتاعة Model عليه `BelongsToTenant`، شغّالة من كود Platform (مفيهوش tenant مربوط)، هترمي `TenantContextMissingException`** — حتى لو الاستعلام الأساسي مش على الـ Model المحمي نفسه. الحل: عد صريح بـ `withoutGlobalScope(BelongsToTenant::class)`.
- **أي جدول DataTables بيستخدم `ajax` بـ `per_page` ثابت (مش `serverSide: true`) هيفقد صفوف فعليًا مع بيانات حقيقية** — مش مجرد بطء، الصفوف بعد الـ per_page (كان 1000) مش هتظهر خالص، ولا في الجدول ولا البحث ولا الفلاتر. أي جدول جديد لازم يستخدم `resources/assets/js/datatables-server-side.js` (helper جاهز) + `serverSide: true` من أول يوم، مش نمط client-side زي القديم. راجع `ARCHITECTURE.md §6`.
- **`$request->string(...)->lower() === 'string_literal'` بترجع false دايمًا** — `->lower()` بترجع `Stringable` object مش string خام، والمقارنة الصارمة `===` بتفشل. استخدم `->toString()` صريحة قبل أي مقارنة `===`/`==`. راجع `ARCHITECTURE.md §6` لباگ حقيقي سببه ده (`sort_dir=desc` كان بيتجاهل تمامًا).
- **أي عمود معروض فعليًا في جدول (خصوصًا أول عمود، زي `code`) لازم يكون جزء من مسار البحث السيرفر-سايد صراحة** — مش بس الحقول "المنطقية" زي name/email. اتكشف مرتين في نفس المراجعة: (1) `plate_letters`+`plate_numbers` بيتعرضوا مجمّعين بمسافة (`AAA 1234`) لكن البحث كان بيقارن كل عمود لوحده — لازم مطابقة على التجميع (`CONCAT()` في MySQL، فرع صريح لـ `||` في SQLite بيئة التستات لإن `CONCAT` مش موجودة فيها أصلًا)؛ (2) عمود `code` في Users/Drivers كان مش داخل الـ `search` خالص. القاعدة العملية: افتح كل `index()` وقارن أعمدة الجدول في الـ Blade/JS مقابل أعمدة الـ `search` في الكنترولر — أي عمود موجود هناك وناقص هنا هو باگ. راجع `ARCHITECTURE.md §6`.
- **عمود "رقم تعريفي/عرض" لأي كيان (مش الـ `id` الخام) يتولّد في `Model::booted()`** عبر `creating()` hook — عشوائي مش متسلسل، فريد على مستوى الـ tenant، خارج الـ `$fillable`. راجع `users.code` كمرجع (`ARCHITECTURE.md §3.8`).
- **إنشاء/تعديل سائق حصريًا عن طريق `/api/v1/drivers` — القرار ده اتصحح 3 مرات قبل ما يستقر، بعد ما اتأكد بنص الـ FRD حرفيًا** (قسم "إدارة وانشاء حسابات السائقين" منفصل تمامًا، Reference URL خاص بيه). متفتحش النقاش ده تاني من غير ما ترجع لنص الـ FRD الأول. راجع `ARCHITECTURE.md §5` للتفاصيل الكاملة والجولات التلاتة.
- **أي تحقق (`in_array` وغيره) على IDs جايين من فورم HTML حقيقي لازم يتعامل معاها كـ string، مش int** — فورم حقيقي (`FormData`) بيبعت كل حاجة كـ string، عكس `postJson()` في التستات اللي بتحافظ على النوع الأصلي (JSON). باگ حقيقي اتكشف بالتجربة الفعلية بالمتصفح مش بالتستات (`in_array($int, $arrayOfStrings, true)` بيفشل دايمًا). اختبر بـ `->post()` بقيم string صراحة، مش `->postJson()` بس. راجع `ARCHITECTURE.md §5`.
- **`Auth::guard('sanctum')->user()` بتكاش المستخدم على مستوى الـ guard instance، مش الطلب** — مشكلة حقيقية بس في PHPUnit Feature tests بتعمل `actingAs()` بمستخدمين مختلفين في نفس الـ test method (مش في التشغيل الحقيقي). لو محتاج تتحقق من المستخدم الحالي بره guard `web` العادي، اعمل تحقق يدوي (زي `EnsureTenant::currentUser()`) بدل `Auth::guard('sanctum')`. راجع `ARCHITECTURE.md §5`.

## سير التحقق القياسي بعد أي تعديل

1. `php artisan test` — لازم كل التستات تعدي، مش بس الجداد.
2. `npm run build` — لو اتغيّر أي JS/SCSS (الـ config/PHP-only edits متحتاجوش build).
3. تحقق فعلي في المتصفح (مش افتراض إن الكود شغال لمجرد إنه اتكتب) — سجّل دخول بالدور المناسب، جرّب السيناريو، افحص الـ DOM/الـ network requests لما يكون ده أوثق من قراءة النص.
4. `php artisan migrate:fresh --seed` في الآخر — ترجيع الداتابيز المحلية لحالة نضيفة بعد أي تجربة يدوية.
