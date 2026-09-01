# CLAUDE.md — مرجع عملي سريع لهذا المشروع

> ملف تشغيلي، مش وثيقة تخطيط — للـ "ليه القرار ده" و"إيه الخطة" شوف `ARCHITECTURE.md` و`WEEKLY_PLAN.md`. الملف ده لأي حاجة اتكشفت بالتجربة الفعلية وممكن تضيّع وقت لو اتنسيت.

## الحالة الحالية

- **الأسبوع 1 (Tenant/Auth) و2 (المستخدمين/الأدوار) خلصوا بالكامل** — 70 Feature test شغالة. راجع `WEEKLY_PLAN.md` لتفاصيل كل أسبوع وإضافاته.
- **الأسبوع 3 — موديول المركبات خلص** (94 Feature test إجمالًا، منها 23 تحت `tests/Feature/Vehicles/`). **موديول مستقل top-level زي Users** — `app/Domain/Vehicles/`، مسار `/app/vehicle/*`، عنصر قائمة "Vehicles" مسطّح؛ **مفيش مجموعة "Fleet"**. **البنية الجديدة:** views في `resources/views/tenant/vehicles/`، web controllers في `app/Http/Controllers/Web/Vehicles/`، routes في `routes/tenant.php` (مطلوبة من `web.php`). (باقي الموديولات — Users/Auth/Profile — هيتنقلوا لنفس البنية مع الـ merge من فرع الزميل.) السائقون والأصول لسه. راجع `WEEKLY_PLAN.md` أسبوع 3 لتفاصيل المؤجَّل (إحصائيات الرحلات) والقرارات (vehicle_categories عالمي، هوية المركبة = اللوحة).
- **إعداد بيئة أول مرة على نسخة جديدة:** `composer install` + `php artisan storage:link` + احذف `public/hot` لو موجود (بيخلي الأصول تحاول تحمّل من vite dev server مش من `public/build`) + `APP_URL=http://localhost:8000` (بالبورت). `maatwebsite/excel` و`barryvdh/laravel-dompdf` في الـ lock لكن التصدير بيرمي 500 لو `vendor/` مش متزامن.
- Company/Contractor مؤجلين للأسبوع 4-5 — أي كود بيفترض وجودهم (فروع الفورم، أدوار العميل) مش مبني لسه عمدًا.

## قبل ما تبدأ أي تعديل

- الـ FRD المرجعي الفعلي هو `GCM_Portal_NewSystem_FRD_V01.06.docx` — **مش** الـ `.pdf` (نصه العربي مش قابل للاستخراج، الخط مشفّر بشكل غريب). لو محتاج تقرأه، فك ضغط الملف كـ zip واستخرج `word/document.xml`، بعدين parse كـ XML — الجداول (`w:tbl`) عناصر منفصلة عن الفقرات (`w:p`)، لازم تمشي على children بالترتيب لو عايز الترتيب الطبيعي للمحتوى محفوظ.
- الداتابيز المحلية (`gcm_dev`) مش معزولة عن التستات — تأكد دايمًا تعمل `php artisan migrate:fresh --seed` في الآخر بعد أي تجربة يدوية بالمتصفح، عشان الداتا التجريبية متتراكمش.

## فخاخ بيئة التطوير (تتسبب في وقت ضايع لو اتنسيت)

1. **أي تعديل على `.env` محتاج إعادة تشغيل السيرفر بالكامل** (مش `config:clear` بس) — `php artisan serve --no-reload` بيستخدم `PHP_CLI_SERVER_WORKERS`، والـ workers بتحتفظ بقيم `putenv()` القديمة على مستوى الـ process. الطريقة: `preview_stop` ثم `preview_start` تاني (أو لو البورت 8000 متمسك بعملية `php.exe` قديمة معلّقة، اقفلها الأول بـ PowerShell `Stop-Process`).
2. **`APP_URL` لازم يشمل البورت** (`http://localhost:8000`) — من غيره أي رابط بيتولّد من `Storage::disk('public')->url()`/`url()`/`route()` بيبقى غلط (بورت 80 مش 8000)، وده بيبان وكإنه "الملف مش بيتحفظ" رغم إنه محفوظ فعليًا على القرص.
3. **الـ `computer` tool (click/type) مش موثوق فيه دايمًا في البيئة دي** — كتير من الأحيان بيحصل click من غير ما يوصل فعليًا (الـ focus بيفضل على `<body>`). لو حصل كده مرتين، متكررش المحاولة — استخدم `javascript_tool` تقرأ الـ value بعد كل خطوة عشان تتأكد، ولو فشل استمر، استخدم `el.value = ...; el.dispatchEvent(new Event('input', {bubbles:true}))` + `form.requestSubmit()` كبديل موثوق.
4. **صفحات فيها أكتر من `<form>`** (زي أي صفحة فيها logout form مخفي في الـ navbar) — `document.querySelector('form')` بيمسك أول واحد في ترتيب الـ DOM مش بالضرورة اللي إنت قاصده. استخدم selector أدق زي `form[action*="..."]`.
5. **`Storage::fake('public')` في التستات بيسقط إعداد `'url'` المخصص** من `config/filesystems.php` — تستات رفع الصور تتأكد من وجود الملف + جزء من الرابط، مش من كونه absolute URL كامل (ده مضمون بس في التشغيل الحقيقي).
6. **`php artisan migrate:fresh` بيصفّر جدول `sessions`** — أي تبويب متصفح مفتوح هيترمي على صفحة تسجيل الدخول تاني بعدها.
7. **Laravel بيحتفظ بجلسة كل guard (`web`/`platform`) بشكل مستقل** — تسجيل دخول على guard مايسجّلش خروج من التاني. أي قرار UI بيعتمد على "مين المستخدم الحالي" (زي القائمة الجانبية) لازم ياخد في الاعتبار الرابط الحالي كمان، مش بس "هل فيه جلسة platform شغالة".
8. **اتجاه الصفحة (RTL/LTR) بيتبع اللغة النشطة server-side** — الـ Template Customizer (اللي كان بيتحكم في الاتجاه عن طريق كوكي `direction`) متشال من المشروع، فأضفنا override في آخر `Helpers::appClasses()`: `textDirection = app()->getLocale() === 'ar' ? 'rtl' : 'ltr'`. أي عمل على الـ layout: متعتمدش على كوكي `direction` ولا على `config('custom.myRTLMode')` — المصدر الوحيد هو اللغة. CSS الـ RTL بيتحمّل دايمًا (`scss/rtl/*` — ملف واحد dual-direction بيتفعّل بـ `<html dir=rtl>`).

## قواعد كود اتأكدت أهميتها بالتجربة (مش نظرية)

- **أي DataTables `render` callback بيرجّع HTML خام لازم يعمل escape لأي حقل مصدره مستخدم** (اسم، إيميل، أي self-service field). DataTables بتحقن الناتج بـ `.html()` مش كنص — استخدم `$('<div>').text(value).html()`. اكتشفنا XSS مخزّن حقيقي بالطريقة دي (مستخدم بأقل صلاحية غيّر اسمه بنفسه، ونفّذ كود في جلسة الـ system_admin وهو بيفتح جدول المستخدمين).
- **أي Model بيستخدم `getRoleNames()`/Spatie `HasRoles`، لازم تعمل `->with('roles')` صريح في أي query بيرجّع مجموعة (list/export)** — من غيرها N+1 كامل (`HasRoles::getRoleNames()` بتعمل `loadMissing('roles')` تلقائيًا لو مش eager-loaded).
- **أي `withCount()` أو subquery على علاقة بتاعة Model عليه `BelongsToTenant`، شغّالة من كود Platform (مفيهوش tenant مربوط)، هترمي `TenantContextMissingException`** — حتى لو الاستعلام الأساسي مش على الـ Model المحمي نفسه. الحل: عد صريح بـ `withoutGlobalScope(BelongsToTenant::class)`.
- **عمود "رقم تعريفي/عرض" لأي كيان (مش الـ `id` الخام) يتولّد في `Model::booted()`** عبر `creating()` hook — عشوائي مش متسلسل، فريد على مستوى الـ tenant، خارج الـ `$fillable`. راجع `users.code` كمرجع (`ARCHITECTURE.md §3.8`).

## سير التحقق القياسي بعد أي تعديل

1. `php artisan test` — لازم كل التستات تعدي، مش بس الجداد.
2. `npm run build` — لو اتغيّر أي JS/SCSS (الـ config/PHP-only edits متحتاجوش build).
3. تحقق فعلي في المتصفح (مش افتراض إن الكود شغال لمجرد إنه اتكتب) — سجّل دخول بالدور المناسب، جرّب السيناريو، افحص الـ DOM/الـ network requests لما يكون ده أوثق من قراءة النص.
4. `php artisan migrate:fresh --seed` في الآخر — ترجيع الداتابيز المحلية لحالة نضيفة بعد أي تجربة يدوية.
